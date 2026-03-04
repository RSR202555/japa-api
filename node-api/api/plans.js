import { prisma } from './_lib/prisma.js';
import { applyCors, json, methodNotAllowed } from './_lib/response.js';

export default async function handler(req, res) {
  applyCors(req, res);
  if (req.method === 'OPTIONS') return json(res, 204, null);
  if (req.method !== 'GET') return methodNotAllowed(res);

  const raw = await prisma.plan.findMany({
    where: { is_active: true },
    orderBy: { price: 'asc' },
    select: {
      id: true,
      name: true,
      slug: true,
      description: true,
      price: true,
      duration_days: true,
      features: true,
      is_active: true,
    },
  });

  // Normaliza tipos: BigInt → Number, Decimal → Number
  const plans = raw.map((p) => ({
    ...p,
    id: Number(p.id),
    price: parseFloat(p.price.toString()),
  }));

  // Frontend espera array direto (não { data: [...] })
  return json(res, 200, plans);
}
