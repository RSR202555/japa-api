import { applyCors, json, methodNotAllowed, readJsonBody } from '../../_lib/response.js';
import { prisma } from '../../_lib/prisma.js';
import { requireAdmin } from '../../_lib/adminAuth.js';
import { z } from 'zod';

const updateSchema = z.object({
  name: z.string().trim().min(2).max(100).optional(),
  description: z.string().max(1000).optional(),
  price: z.number().positive().optional(),
  duration_days: z.number().int().positive().optional(),
  features: z.array(z.string()).optional(),
  is_active: z.boolean().optional(),
});

function serializePlan(p) {
  return {
    id: Number(p.id),
    name: p.name,
    slug: p.slug,
    description: p.description,
    price: parseFloat(p.price.toString()),
    duration_days: p.duration_days,
    features: p.features ?? [],
    is_active: p.is_active,
    created_at: p.created_at,
  };
}

export default async function handler(req, res) {
  applyCors(req, res);
  if (req.method === 'OPTIONS') return json(res, 204, null);

  const auth = await requireAdmin(req, res);
  if (!auth) return;

  const planId = BigInt(req.query.id);
  const plan = await prisma.plan.findUnique({ where: { id: planId } });
  if (!plan) return json(res, 404, { message: 'Plano não encontrado.' });

  if (req.method === 'GET') {
    return json(res, 200, serializePlan(plan));
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

    const updated = await prisma.plan.update({ where: { id: planId }, data: parsed });
    return json(res, 200, serializePlan(updated));
  }

  if (req.method === 'DELETE') {
    await prisma.plan.update({ where: { id: planId }, data: { is_active: false } });
    return json(res, 200, { message: 'Plano desativado.' });
  }

  return methodNotAllowed(res);
}
