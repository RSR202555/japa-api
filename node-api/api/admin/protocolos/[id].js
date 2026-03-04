import { applyCors, json, methodNotAllowed, readJsonBody } from '../../_lib/response.js';
import { prisma } from '../../_lib/prisma.js';
import { requireAdmin } from '../../_lib/adminAuth.js';
import { z } from 'zod';

const updateSchema = z.object({
  title: z.string().trim().min(2).max(255).optional(),
  type: z.enum(['treino', 'dieta', 'full']).optional(),
  content: z.object({
    treino: z.any().optional(),
    dieta: z.any().optional(),
  }).optional(),
  is_template: z.boolean().optional(),
});

function serializeProtocol(p) {
  return {
    id: Number(p.id),
    title: p.title,
    type: p.type,
    content: p.content,
    is_template: p.is_template,
    created_by: Number(p.created_by),
    created_at: p.created_at,
    updated_at: p.updated_at,
  };
}

export default async function handler(req, res) {
  applyCors(req, res);
  if (req.method === 'OPTIONS') return json(res, 204, null);

  const auth = await requireAdmin(req, res);
  if (!auth) return;

  const protocolId = BigInt(req.query.id);
  const protocol = await prisma.protocol.findUnique({ where: { id: protocolId } });
  if (!protocol) return json(res, 404, { message: 'Protocolo não encontrado.' });

  if (req.method === 'GET') {
    return json(res, 200, serializeProtocol(protocol));
  }

  if (req.method === 'PUT') {
    let body;
    try {
      body = (await readJsonBody(req)) ?? {};
    } catch {
      return json(res, 400, { message: 'JSON inválido.' });
    }

    let parsed;
    try {
      parsed = updateSchema.parse(body);
    } catch (e) {
      return json(res, 422, { message: 'Dados inválidos.', errors: e?.errors ?? [] });
    }

    const updated = await prisma.protocol.update({
      where: { id: protocolId },
      data: { ...parsed, updated_at: new Date() },
    });
    return json(res, 200, serializeProtocol(updated));
  }

  if (req.method === 'DELETE') {
    // Desativa atribuições antes de deletar
    await prisma.protocolAssignment.updateMany({
      where: { protocol_id: protocolId },
      data: { is_active: false },
    });
    await prisma.protocol.delete({ where: { id: protocolId } });
    return json(res, 200, { message: 'Protocolo removido.' });
  }

  return methodNotAllowed(res);
}
