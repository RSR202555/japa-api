import bcrypt from 'bcryptjs';
import { z } from 'zod';
import { prisma } from '../_lib/prisma.js';
import { applyCors, json, methodNotAllowed, readJsonBody } from '../_lib/response.js';
import { signAccessToken } from '../_lib/auth.js';

const bodySchema = z
  .object({
    name: z.string().trim().min(3).max(100),
    email: z.string().trim().toLowerCase().email().max(255),
    password: z
      .string()
      .min(8)
      .max(128)
      .regex(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/),
    password_confirmation: z.string().min(1),
    phone: z.string().trim().max(20).optional(),
    date_of_birth: z.string().optional()
  })
  .superRefine((val, ctx) => {
    if (val.password !== val.password_confirmation) {
      ctx.addIssue({ code: 'custom', message: 'As senhas não coincidem.', path: ['password_confirmation'] });
    }
  });

export default async function handler(req, res) {
  applyCors(req, res);
  if (req.method === 'OPTIONS') return json(res, 204, null);
  if (req.method !== 'POST') {
    return json(res, 405, {
      message: 'Method not allowed. Use POST with application/json.'
    });
  }

  try {
    if (!process.env.DATABASE_URL) {
      return json(res, 500, { message: 'Missing DATABASE_URL env var.' });
    }
    if (!process.env.JWT_SECRET) {
      return json(res, 500, { message: 'Missing JWT_SECRET env var.' });
    }

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

    const existing = await prisma.user.findUnique({ where: { email: parsed.email } });
    if (existing) {
      return json(res, 409, { message: 'Este e-mail já está cadastrado.' });
    }

    const passwordHash = await bcrypt.hash(parsed.password, 12);

    const user = await prisma.user.create({
      data: {
        name: parsed.name,
        email: parsed.email,
        password: passwordHash,
        phone: parsed.phone ?? null,
        date_of_birth: parsed.date_of_birth ? new Date(parsed.date_of_birth) : null,
        is_active: true
      },
      select: { id: true, name: true, email: true, phone: true, date_of_birth: true, avatar_url: true, is_active: true, created_at: true }
    });

    const role = await prisma.role.upsert({
      where: { name_guard_name: { name: 'aluno', guard_name: 'web' } },
      update: {},
      create: { name: 'aluno', guard_name: 'web' }
    });

    await prisma.modelHasRole.create({
      data: { role_id: role.id, model_type: 'App\\Models\\User', model_id: user.id }
    });

    const token = signAccessToken({ sub: user.id.toString() });

    return json(res, 201, {
      message: 'Conta criada com sucesso.',
      user,
      token
    });
  } catch (e) {
    return json(res, 500, {
      message: 'Internal server error.',
      error: e?.message ?? String(e)
    });
  }
}
