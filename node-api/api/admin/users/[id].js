import { applyCors, json, methodNotAllowed } from '../../_lib/response.js';
import { prisma } from '../../_lib/prisma.js';
import { requireAdmin } from '../../_lib/adminAuth.js';

export default async function handler(req, res) {
  applyCors(req, res);
  if (req.method === 'OPTIONS') return json(res, 204, null);
  if (req.method !== 'GET') return methodNotAllowed(res);

  const auth = await requireAdmin(req, res);
  if (!auth) return;

  const userId = BigInt(req.query.id);

  const user = await prisma.user.findUnique({
    where: { id: userId },
    include: {
      subscriptions: {
        orderBy: { created_at: 'desc' },
        take: 1,
        include: { plan: true },
      },
      protocolAssignments: {
        where: { is_active: true },
        orderBy: { assigned_at: 'desc' },
        take: 1,
        include: { protocol: true },
      },
    },
  });

  if (!user) return json(res, 404, { message: 'Aluno não encontrado.' });

  const sub = user.subscriptions[0] ?? null;
  const assignment = user.protocolAssignments[0] ?? null;

  return json(res, 200, {
    user: {
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
            starts_at: sub.starts_at,
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
    },
    protocol_assignment: assignment
      ? {
          id: Number(assignment.id),
          notes: assignment.notes,
          assigned_at: assignment.assigned_at,
          protocol: {
            id: Number(assignment.protocol.id),
            title: assignment.protocol.title,
            type: assignment.protocol.type,
            content: assignment.protocol.content,
          },
        }
      : null,
  });
}
