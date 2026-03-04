import { applyCors, json, methodNotAllowed } from '../_lib/response.js';
import { prisma } from '../_lib/prisma.js';
import { requireAuth } from '../_lib/adminAuth.js';

export default async function handler(req, res) {
  applyCors(req, res);
  if (req.method === 'OPTIONS') return json(res, 204, null);
  if (req.method !== 'GET') return methodNotAllowed(res);

  const auth = await requireAuth(req, res);
  if (!auth) return;

  const assignment = await prisma.protocolAssignment.findFirst({
    where: { student_id: auth.userId, is_active: true },
    orderBy: { assigned_at: 'desc' },
    include: { protocol: true },
  });

  if (!assignment) return json(res, 200, { assignment: null });

  return json(res, 200, {
    assignment: {
      id: Number(assignment.id),
      notes: assignment.notes,
      assigned_at: assignment.assigned_at,
      protocol: {
        id: Number(assignment.protocol.id),
        title: assignment.protocol.title,
        type: assignment.protocol.type,
        content: assignment.protocol.content,
      },
    },
  });
}
