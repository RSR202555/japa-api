import bcrypt from 'bcryptjs';
import { z } from 'zod';
import { prisma } from '../_lib/prisma.js';
import { applyCors, json, methodNotAllowed, readJsonBody } from '../_lib/response.js';
import { signAccessToken } from '../_lib/auth.js';

const bodySchema = z.object({
  secret: z.string().min(1),
  name: z.string().trim().min(3).max(100),
  email: z.string().trim().toLowerCase().email().max(255),
  password: z.string().min(8).max(128),
});

export default async function handler(req, res) {
  applyCors(req, res);
  if (req.method === 'OPTIONS') return json(res, 204, null);
  if (req.method !== 'POST') return methodNotAllowed(res);

  let body;
  try {
    body = (await readJsonBody(req)) ?? {};
  } catch {
    return json(res, 400, { message: 'Invalid JSON body.' });
  }

  let parsed;
  try {
    parsed = bodySchema.parse(body);
  } catch (e) {
    return json(res, 422, { message: 'Validation error', errors: e?.errors ?? [] });
  }

  // Valida o secret definido nas env vars do Vercel
  const seedSecret = process.env.SEED_SECRET;
  if (!seedSecret || parsed.secret !== seedSecret) {
    return json(res, 403, { message: 'Secret inválido.' });
  }

  // Impede duplicata: verifica se já existe algum admin
  const adminRole = await prisma.role.findFirst({
    where: { name: 'admin', guard_name: 'web' },
  });

  if (adminRole) {
    const existing = await prisma.modelHasRole.findFirst({
      where: { role_id: adminRole.id, model_type: 'App\\Models\\User' },
    });
    if (existing) {
      return json(res, 409, { message: 'Já existe um admin cadastrado.' });
    }
  }

  // Verifica se o e-mail já está em uso
  const emailTaken = await prisma.user.findUnique({ where: { email: parsed.email } });
  if (emailTaken) {
    return json(res, 409, { message: 'E-mail já cadastrado.' });
  }

  const passwordHash = await bcrypt.hash(parsed.password, 12);

  const user = await prisma.user.create({
    data: {
      name: parsed.name,
      email: parsed.email,
      password: passwordHash,
      is_active: true,
    },
  });

  // Cria role admin se não existir
  const role = await prisma.role.upsert({
    where: { name_guard_name: { name: 'admin', guard_name: 'web' } },
    update: {},
    create: { name: 'admin', guard_name: 'web' },
  });

  await prisma.modelHasRole.create({
    data: {
      role_id: role.id,
      model_type: 'App\\Models\\User',
      model_id: user.id,
    },
  });

  const token = signAccessToken({ sub: user.id.toString() });

  return json(res, 201, {
    message: 'Admin criado com sucesso.',
    user: { id: Number(user.id), name: user.name, email: user.email },
    token,
  });
}
