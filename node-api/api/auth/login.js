import bcrypt from 'bcryptjs';
import { z } from 'zod';
import { prisma } from '../_lib/prisma.js';
import { applyCors, json, methodNotAllowed, readJsonBody } from '../_lib/response.js';
import { signAccessToken } from '../_lib/auth.js';

const bodySchema = z.object({
  email: z.string().trim().toLowerCase().email().max(255),
  password: z.string().min(1).max(128)
});

export default async function handler(req, res) {
  applyCors(req, res);
  if (req.method === 'OPTIONS') return json(res, 204, null);
  if (req.method !== 'POST') return methodNotAllowed(res);

  let body;
  try {
    body = (await readJsonBody(req)) ?? req.body ?? {};
  } catch {
    return json(res, 400, { message: 'Invalid JSON body.' });
  }

  let parsed;
  try {
    parsed = bodySchema.parse(body);
  } catch (e) {
    return json(res, 422, { message: 'Validation error', errors: e?.errors ?? [] });
  }

  const user = await prisma.user.findUnique({
    where: { email: parsed.email }
  });

  if (!user) {
    return json(res, 422, { message: 'Credenciais inválidas.' });
  }

  const ok = await bcrypt.compare(parsed.password, user.password);
  if (!ok) {
    return json(res, 422, { message: 'Credenciais inválidas.' });
  }

  if (!user.is_active) {
    return json(res, 403, { message: 'Conta desativada. Entre em contato com o suporte.' });
  }

  const token = signAccessToken({ sub: user.id.toString() });

  // Busca roles do usuário para redirecionamento no frontend
  const roleAssignments = await prisma.modelHasRole.findMany({
    where: { model_id: user.id, model_type: 'App\\Models\\User' },
    include: { role: true },
  });

  return json(res, 200, {
    message: 'Login realizado com sucesso.',
    user: {
      id: Number(user.id),
      name: user.name,
      email: user.email,
      phone: user.phone,
      date_of_birth: user.date_of_birth,
      avatar_url: user.avatar_url,
      is_active: user.is_active,
      roles: roleAssignments.map((r) => r.role.name),
      email_verified: user.email_verified_at !== null,
      subscription: null,
    },
    token
  });
}
