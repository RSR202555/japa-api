import { applyCors, json, methodNotAllowed } from '../_lib/response.js';
import { prisma } from '../_lib/prisma.js';
import { requireAdmin } from '../_lib/adminAuth.js';

export default async function handler(req, res) {
  applyCors(req, res);
  if (req.method === 'OPTIONS') return json(res, 204, null);
  if (req.method !== 'GET') return methodNotAllowed(res);

  const auth = await requireAdmin(req, res);
  if (!auth) return;

  const now = new Date();
  const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
  const startOfLastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  const endOfLastMonth = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59);

  // IDs dos admins para excluir das contagens de alunos
  const adminRoles = await prisma.modelHasRole.findMany({
    where: { model_type: 'App\\Models\\User', role: { name: 'admin' } },
    select: { model_id: true },
  });
  const adminIds = adminRoles.map((r) => r.model_id);
  const excludeAdmins = adminIds.length > 0 ? { id: { notIn: adminIds } } : {};

  const [totalStudents, activeStudents, newStudents] = await Promise.all([
    prisma.user.count({ where: excludeAdmins }),
    prisma.user.count({ where: { ...excludeAdmins, is_active: true } }),
    prisma.user.count({ where: { ...excludeAdmins, created_at: { gte: startOfMonth } } }),
  ]);

  let subStats = { active: 0, expired: 0, pending: 0, cancelled: 0 };
  let revenue = { this_month: 0, last_month: 0, total: 0 };
  let recentTransactions = [];

  try {
    const [active, expired, pending, cancelled,
           thisMonthRev, lastMonthRev, totalRev, recent] = await Promise.all([
      prisma.subscription.count({ where: { status: 'active' } }),
      prisma.subscription.count({ where: { status: 'expired' } }),
      prisma.subscription.count({ where: { status: 'pending' } }),
      prisma.subscription.count({ where: { status: 'cancelled' } }),
      prisma.transaction.aggregate({
        where: { status: 'paid', paid_at: { gte: startOfMonth } },
        _sum: { amount: true },
      }),
      prisma.transaction.aggregate({
        where: { status: 'paid', paid_at: { gte: startOfLastMonth, lte: endOfLastMonth } },
        _sum: { amount: true },
      }),
      prisma.transaction.aggregate({
        where: { status: 'paid' },
        _sum: { amount: true },
      }),
      prisma.transaction.findMany({
        orderBy: { created_at: 'desc' },
        take: 10,
        include: { user: { select: { name: true } } },
      }),
    ]);

    subStats = { active, expired, pending, cancelled };
    revenue = {
      this_month: parseFloat((thisMonthRev._sum.amount ?? 0).toString()),
      last_month: parseFloat((lastMonthRev._sum.amount ?? 0).toString()),
      total: parseFloat((totalRev._sum.amount ?? 0).toString()),
    };
    recentTransactions = recent.map((tx) => ({
      id: Number(tx.id),
      user_id: Number(tx.user_id),
      user_name: tx.user?.name ?? null,
      amount: parseFloat(tx.amount.toString()),
      status: tx.status,
      payment_method: tx.payment_method,
      paid_at: tx.paid_at,
      created_at: tx.created_at,
    }));
  } catch {
    // Tabelas podem não existir ainda — retorna zeros
  }

  return json(res, 200, {
    students: { total: totalStudents, active: activeStudents, new_this_month: newStudents },
    subscriptions: subStats,
    revenue,
    recent_transactions: recentTransactions,
  });
}
