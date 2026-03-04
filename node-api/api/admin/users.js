import { applyCors, json, methodNotAllowed } from '../_lib/response.js';
import { prisma } from '../_lib/prisma.js';
import { requireAdmin } from '../_lib/adminAuth.js';

function serializeUser(user) {
  const sub = user.subscriptions?.[0] ?? null;
  return {
    id: Number(user.id),
    name: user.name,
    email: user.email,
    phone: user.phone,
    date_of_birth: user.date_of_birth,
    avatar_url: user.avatar_url,
    is_active: user.is_active,
    email_verified: user.email_verified_at !== null,
    created_at: user.created_at,
    roles: [],
    subscription: sub
      ? {
          id: Number(sub.id),
          status: sub.status,
          expires_at: sub.expires_at,
          is_active: sub.status === 'active',
          plan: sub.plan
            ? {
                id: Number(sub.plan.id),
                name: sub.plan.name,
                price: parseFloat(sub.plan.price.toString()),
              }
            : null,
        }
      : null,
  };
}

export default async function handler(req, res) {
  applyCors(req, res);
  if (req.method === 'OPTIONS') return json(res, 204, null);
  if (req.method !== 'GET') return methodNotAllowed(res);

  const auth = await requireAdmin(req, res);
  if (!auth) return;

  const { search = '', page = '1' } = req.query;
  const perPage = 20;
  const currentPage = Math.max(1, parseInt(page, 10) || 1);

  const adminRoles = await prisma.modelHasRole.findMany({
    where: { model_type: 'App\\Models\\User', role: { name: 'admin' } },
    select: { model_id: true },
  });
  const adminIds = adminRoles.map((r) => r.model_id);

  const where = {
    ...(adminIds.length > 0 ? { id: { notIn: adminIds } } : {}),
    ...(search
      ? { OR: [{ name: { contains: search } }, { email: { contains: search } }] }
      : {}),
  };

  const [total, users] = await Promise.all([
    prisma.user.count({ where }),
    prisma.user.findMany({
      where,
      include: {
        subscriptions: {
          orderBy: { created_at: 'desc' },
          take: 1,
          include: { plan: true },
        },
      },
      orderBy: { created_at: 'desc' },
      skip: (currentPage - 1) * perPage,
      take: perPage,
    }),
  ]);

  return json(res, 200, {
    data: users.map(serializeUser),
    meta: {
      total,
      per_page: perPage,
      current_page: currentPage,
      last_page: Math.max(1, Math.ceil(total / perPage)),
    },
  });
}
