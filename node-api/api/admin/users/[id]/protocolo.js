import { applyCors, json, methodNotAllowed, readJsonBody } from '../../../_lib/response.js';
import { prisma } from '../../../_lib/prisma.js';
import { requireAdmin } from '../../../_lib/adminAuth.js';
import { z } from 'zod';

const assignSchema = z.object({
  protocol_id: z.number().int().positive(),
  notes: z.string().max(1000).optional().default(''),
});

export default async function handler(req, res) {
  applyCors(req, res);
  if (req.method === 'OPTIONS') return json(res, 204, null);

  const auth = await requireAdmin(req, res);
  if (!auth) return;

  const studentId = BigInt(req.query.id);

  // GET — retorna protocolo ativo do aluno
  if (req.method === 'GET') {
    const assignment = await prisma.protocolAssignment.findFirst({
      where: { student_id: studentId, is_active: true },
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

  // POST — atribui protocolo ao aluno
  if (req.method === 'POST') {
    let body;
    try {
      body = (await readJsonBody(req)) ?? {};
    } catch {
      return json(res, 400, { message: 'JSON inválido.' });
    }

    let parsed;
    try {
      parsed = assignSchema.parse(body);
    } catch (e) {
      return json(res, 422, { message: 'Dados inválidos.', errors: e?.errors ?? [] });
    }

    // Verifica se o aluno existe
    const student = await prisma.user.findUnique({ where: { id: studentId } });
    if (!student) return json(res, 404, { message: 'Aluno não encontrado.' });

    // Verifica se o protocolo existe
    const protocol = await prisma.protocol.findUnique({
      where: { id: BigInt(parsed.protocol_id) },
    });
    if (!protocol) return json(res, 404, { message: 'Protocolo não encontrado.' });

    // Desativa atribuições anteriores
    await prisma.protocolAssignment.updateMany({
      where: { student_id: studentId, is_active: true },
      data: { is_active: false },
    });

    // Cria nova atribuição
    const assignment = await prisma.protocolAssignment.create({
      data: {
        protocol_id: BigInt(parsed.protocol_id),
        student_id: studentId,
        assigned_by: auth.userId,
        notes: parsed.notes,
        is_active: true,
      },
    });

    return json(res, 201, {
      message: 'Protocolo atribuído com sucesso.',
      assignment: {
        id: Number(assignment.id),
        protocol_id: Number(assignment.protocol_id),
        notes: assignment.notes,
        assigned_at: assignment.assigned_at,
      },
    });
  }

  return methodNotAllowed(res);
}
