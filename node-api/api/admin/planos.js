import { applyCors, json, methodNotAllowed, readJsonBody } from '../_lib/response.js';
import { prisma } from '../_lib/prisma.js';
import { requireAdmin } from '../_lib/adminAuth.js';
import { z } from 'zod';

const planSchema = z.object({
  name: z.string().trim().min(2).max(100),
  slug: z.string().trim().min(2).max(100).regex(/^[a-z0-9-]+$/),
  description: z.string().max(1000).optional().default(''),
  price: z.number().positive(),
  duration_days: z.number().int().positive().default(30),
  features: z.array(z.string()).default([]),
  is_active: z.boolean().default(true),
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

  // GET — listar planos (sem auth para uso público também)
  if (req.method === 'GET') {
    const { all } = req.query;
    const plans = await prisma.plan.findMany({
      where: all === '1' ? {} : { is_active: true },
      orderBy: { price: 'asc' },
    });
    return json(res, 200, plans.map(serializePlan));
  }

  // POST — criar plano (requer admin)
  if (req.method === 'POST') {
    const auth = await requireAdmin(req, res);
    if (!auth) return;

    let body;
    try {
      body = (await readJsonBody(req)) ?? {};
    } catch {
      return json(res, 400, { message: 'JSON inválido.' });
    }

    let parsed;
    try {
      parsed = planSchema.parse(body);
    } catch (e) {
      return json(res, 422, { message: 'Dados inválidos.', errors: e?.errors ?? [] });
    }

    const existing = await prisma.plan.findUnique({ where: { slug: parsed.slug } });
    if (existing) return json(res, 409, { message: 'Slug já em uso.' });

    const plan = await prisma.plan.create({ data: parsed });
    return json(res, 201, serializePlan(plan));
  }

  return methodNotAllowed(res);
}
