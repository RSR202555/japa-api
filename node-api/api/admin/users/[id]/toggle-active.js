import { applyCors, json, methodNotAllowed } from '../../../_lib/response.js';
import { prisma } from '../../../_lib/prisma.js';
import { requireAdmin } from '../../../_lib/adminAuth.js';

export default async function handler(req, res) {
  applyCors(req, res);
  if (req.method === 'OPTIONS') return json(res, 204, null);
  if (req.method !== 'PATCH') return methodNotAllowed(res);

  const auth = await requireAdmin(req, res);
  if (!auth) return;

  const userId = BigInt(req.query.id);

  const user = await prisma.user.findUnique({ where: { id: userId } });
  if (!user) return json(res, 404, { message: 'Aluno não encontrado.' });

  const updated = await prisma.user.update({
    where: { id: userId },
    data: { is_active: !user.is_active },
  });

  return json(res, 200, {
    message: updated.is_active ? 'Aluno ativado.' : 'Aluno desativado.',
    is_active: updated.is_active,
  });
}
