import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import { z } from 'zod';
import { PrismaClient } from '@prisma/client';
import crypto from 'crypto';

const globalForPrisma = globalThis;
const prisma = globalForPrisma.__prisma ?? new PrismaClient();
if (process.env.NODE_ENV !== 'production') globalForPrisma.__prisma = prisma;

// ── Utilities ────────────────────────────────────────────────────────────────

function json(res, status, body) {
  res.statusCode = status;
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.end(JSON.stringify(body, (_k, v) => (typeof v === 'bigint' ? Number(v) : v)));
}

function applyCors(req, res) {
  const origin = req.headers?.origin;
  if (origin) {
    res.setHeader('Access-Control-Allow-Origin', origin);
    res.setHeader('Vary', 'Origin');
  } else {
    res.setHeader('Access-Control-Allow-Origin', '*');
  }
  res.setHeader('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE,OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  res.setHeader('Access-Control-Allow-Credentials', 'true');
}

async function readJsonBody(req) {
  const ct = String(req.headers['content-type'] || '').toLowerCase();
  if (!ct.includes('application/json')) return null;
  const chunks = [];
  for await (const chunk of req) chunks.push(Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk));
  const raw = Buffer.concat(chunks).toString('utf8').trim();
  if (!raw) return {};
  return JSON.parse(raw);
}

function getBearerToken(req) {
  const h = req.headers?.authorization || '';
  const m = h.match(/^Bearer\s+(.+)$/i);
  return m ? m[1] : null;
}

function signToken(payload) {
  const secret = process.env.JWT_SECRET;
  if (!secret) throw new Error('Missing JWT_SECRET');
  return jwt.sign(payload, secret, { expiresIn: '60m' });
}

function verifyToken(token) {
  const secret = process.env.JWT_SECRET;
  if (!secret) throw new Error('Missing JWT_SECRET');
  return jwt.verify(token, secret);
}

function env(name, fallback = null) {
  const v = process.env[name];
  return v === undefined || v === '' ? fallback : v;
}

function makeOrderNsu() {
  return crypto.randomUUID();
}

async function fetchJson(url, options) {
  const res = await fetch(url, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...(options?.headers ?? {}),
    },
  });
  const text = await res.text();
  let data = null;
  try { data = text ? JSON.parse(text) : null; } catch { data = text; }
  if (!res.ok) {
    const err = new Error('Request failed');
    err.status = res.status;
    err.data = data;
    throw err;
  }
  return data;
}

async function requireAdmin(req, res) {
  const token = getBearerToken(req);
  if (!token) { json(res, 401, { message: 'Não autorizado.' }); return null; }
  let payload;
  try { payload = verifyToken(token); } catch { json(res, 401, { message: 'Token inválido ou expirado.' }); return null; }
  const userId = BigInt(payload.sub);
  const role = await prisma.modelHasRole.findFirst({
    where: { model_id: userId, model_type: 'App\\Models\\User', role: { name: 'admin' } },
  });
  if (!role) { json(res, 403, { message: 'Acesso negado.' }); return null; }
  return { userId };
}

async function requireAuth(req, res) {
  const token = getBearerToken(req);
  if (!token) { json(res, 401, { message: 'Não autorizado.' }); return null; }
  let payload;
  try { payload = verifyToken(token); } catch { json(res, 401, { message: 'Token inválido ou expirado.' }); return null; }
  return { userId: BigInt(payload.sub) };
}

// ── Serializers ──────────────────────────────────────────────────────────────

function serializeUser(user, sub = null) {
  return {
    id: Number(user.id),
    name: user.name,
    email: user.email,
    phone: user.phone,
    date_of_birth: user.date_of_birth,
    avatar_url: user.avatar_url,
    is_active: user.is_active,
    email_verified: user.email_verified_at !== null,
    created_at: user.created_at,
    roles: [],
    subscription: sub
      ? {
          id: Number(sub.id),
          status: sub.status,
          expires_at: sub.expires_at,
          starts_at: sub.starts_at,
          is_active: sub.status === 'active',
          plan: sub.plan
            ? { id: Number(sub.plan.id), name: sub.plan.name, price: parseFloat(sub.plan.price.toString()) }
            : null,
        }
      : null,
  };
}

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

// ── Handlers ─────────────────────────────────────────────────────────────────

async function handleLogin(req, res) {
  if (req.method !== 'POST') return json(res, 405, { message: 'Method not allowed' });
  const schema = z.object({
    email: z.string().trim().toLowerCase().email().max(255),
    password: z.string().min(1).max(128),
  });
  let body;
  try { body = (await readJsonBody(req)) ?? {}; } catch { return json(res, 400, { message: 'Invalid JSON.' }); }
  let parsed;
  try { parsed = schema.parse(body); } catch (e) { return json(res, 422, { message: 'Validation error', errors: e?.errors ?? [] }); }

  const user = await prisma.user.findUnique({ where: { email: parsed.email } });
  if (!user) return json(res, 422, { message: 'Credenciais inválidas.' });
  const ok = await bcrypt.compare(parsed.password, user.password);
  if (!ok) return json(res, 422, { message: 'Credenciais inválidas.' });
  if (!user.is_active) return json(res, 403, { message: 'Conta desativada. Entre em contato com o suporte.' });

  const token = signToken({ sub: user.id.toString() });
  const roles = await prisma.modelHasRole.findMany({
    where: { model_id: user.id, model_type: 'App\\Models\\User' },
    include: { role: true },
  });

  return json(res, 200, {
    message: 'Login realizado com sucesso.',
    user: { id: Number(user.id), name: user.name, email: user.email, phone: user.phone, date_of_birth: user.date_of_birth, avatar_url: user.avatar_url, is_active: user.is_active, roles: roles.map(r => r.role.name), email_verified: user.email_verified_at !== null, subscription: null },
    token,
  });
}

async function handleMe(req, res) {
  if (req.method !== 'GET') return json(res, 405, { message: 'Method not allowed' });
  const token = getBearerToken(req);
  if (!token) return json(res, 401, { message: 'Unauthenticated.' });
  let decoded;
  try { decoded = verifyToken(token); } catch { return json(res, 401, { message: 'Invalid token.' }); }
  const userId = BigInt(decoded.sub);
  const user = await prisma.user.findUnique({
    where: { id: userId },
    select: { id: true, name: true, email: true, email_verified_at: true, phone: true, date_of_birth: true, avatar_url: true, is_active: true, created_at: true },
  });
  if (!user) return json(res, 404, { message: 'Usuário não encontrado.' });
  const roles = await prisma.modelHasRole.findMany({
    where: { model_id: userId, model_type: 'App\\Models\\User' },
    include: { role: true },
  });
  return json(res, 200, {
    user: { ...user, id: Number(user.id), roles: roles.map(r => r.role.name), email_verified: user.email_verified_at !== null, subscription: null },
  });
}

async function handleDashboard(req, res) {
  if (req.method !== 'GET') return json(res, 405, { message: 'Method not allowed' });
  const auth = await requireAdmin(req, res);
  if (!auth) return;

  const now = new Date();
  const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
  const startOfLastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  const endOfLastMonth = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59);

  const adminRoles = await prisma.modelHasRole.findMany({
    where: { model_type: 'App\\Models\\User', role: { name: 'admin' } },
    select: { model_id: true },
  });
  const adminIds = adminRoles.map(r => r.model_id);
  const excl = adminIds.length > 0 ? { id: { notIn: adminIds } } : {};

  const [total, active, newMonth] = await Promise.all([
    prisma.user.count({ where: excl }),
    prisma.user.count({ where: { ...excl, is_active: true } }),
    prisma.user.count({ where: { ...excl, created_at: { gte: startOfMonth } } }),
  ]);

  let subStats = { active: 0, expired: 0, pending: 0, cancelled: 0 };
  let revenue = { this_month: 0, last_month: 0, total: 0 };
  let recentTransactions = [];
  try {
    const [sa, se, sp, sc, rm, rl, rt, recent] = await Promise.all([
      prisma.subscription.count({ where: { status: 'active' } }),
      prisma.subscription.count({ where: { status: 'expired' } }),
      prisma.subscription.count({ where: { status: 'pending' } }),
      prisma.subscription.count({ where: { status: 'cancelled' } }),
      prisma.transaction.aggregate({ where: { status: 'paid', paid_at: { gte: startOfMonth } }, _sum: { amount: true } }),
      prisma.transaction.aggregate({ where: { status: 'paid', paid_at: { gte: startOfLastMonth, lte: endOfLastMonth } }, _sum: { amount: true } }),
      prisma.transaction.aggregate({ where: { status: 'paid' }, _sum: { amount: true } }),
      prisma.transaction.findMany({ orderBy: { created_at: 'desc' }, take: 10, include: { user: { select: { name: true } } } }),
    ]);
    subStats = { active: sa, expired: se, pending: sp, cancelled: sc };
    revenue = {
      this_month: parseFloat((rm._sum.amount ?? 0).toString()),
      last_month: parseFloat((rl._sum.amount ?? 0).toString()),
      total: parseFloat((rt._sum.amount ?? 0).toString()),
    };
    recentTransactions = recent.map(tx => ({
      id: Number(tx.id), user_id: Number(tx.user_id), user_name: tx.user?.name ?? null,
      amount: parseFloat(tx.amount.toString()), status: tx.status,
      payment_method: tx.payment_method, paid_at: tx.paid_at, created_at: tx.created_at,
    }));
  } catch {}

  return json(res, 200, {
    students: { total, active, new_this_month: newMonth },
    subscriptions: subStats,
    revenue,
    recent_transactions: recentTransactions,
  });
}

async function handleAdminUsers(req, res, query) {
  const auth = await requireAdmin(req, res);
  if (!auth) return;

  if (req.method === 'GET') {
    const { search = '', page = '1', active } = query;
    const perPage = 20;
    const currentPage = Math.max(1, parseInt(page, 10) || 1);
    const adminRoles = await prisma.modelHasRole.findMany({
      where: { model_type: 'App\\Models\\User', role: { name: 'admin' } },
      select: { model_id: true },
    });
    const adminIds = adminRoles.map(r => r.model_id);
    const where = {
      ...(adminIds.length > 0 ? { id: { notIn: adminIds } } : {}),
      ...(search ? { OR: [{ name: { contains: search } }, { email: { contains: search } }] } : {}),
      ...(active !== undefined ? { is_active: active === 'true' } : {}),
    };
    const [total, users] = await Promise.all([
      prisma.user.count({ where }),
      prisma.user.findMany({
        where,
        include: { subscriptions: { orderBy: { created_at: 'desc' }, take: 1, include: { plan: true } } },
        orderBy: { created_at: 'desc' },
        skip: (currentPage - 1) * perPage,
        take: perPage,
      }),
    ]);
    return json(res, 200, {
      data: users.map(u => serializeUser(u, u.subscriptions?.[0] ?? null)),
      meta: { total, per_page: perPage, current_page: currentPage, last_page: Math.max(1, Math.ceil(total / perPage)) },
    });
  }

  if (req.method === 'POST') {
    const schema = z.object({
      name: z.string().min(1).max(255),
      email: z.string().email().max(255),
      password: z.string().min(8),
      phone: z.string().max(30).optional().nullable(),
      plan_id: z.number().int().positive().optional().nullable(),
      subscription_status: z.enum(['active', 'pending']).optional().default('active'),
      subscription_starts_at: z.string().optional().nullable(),
    });
    let body;
    try { body = (await readJsonBody(req)) ?? {}; } catch { return json(res, 400, { message: 'JSON inválido.' }); }
    let parsed;
    try { parsed = schema.parse(body); } catch (e) { return json(res, 422, { message: 'Dados inválidos.', errors: e?.errors ?? [] }); }

    const existing = await prisma.user.findUnique({ where: { email: parsed.email } });
    if (existing) return json(res, 422, { message: 'E-mail já cadastrado.' });

    const hashedPassword = await bcrypt.hash(parsed.password, 12);
    const user = await prisma.user.create({
      data: { name: parsed.name, email: parsed.email, password: hashedPassword, phone: parsed.phone ?? null, is_active: true, created_at: new Date(), updated_at: new Date() },
    });

    const alunoRole = await prisma.role.findFirst({ where: { name: 'aluno' } });
    if (alunoRole) {
      await prisma.modelHasRole.create({
        data: { role_id: alunoRole.id, model_type: 'App\\Models\\User', model_id: user.id },
      });
    }

    let subscription = null;
    if (parsed.plan_id) {
      const plan = await prisma.plan.findUnique({ where: { id: BigInt(parsed.plan_id) } });
      if (plan) {
        const startsAt = parsed.subscription_starts_at ? new Date(parsed.subscription_starts_at) : new Date();
        const expiresAt = new Date(startsAt.getTime() + plan.duration_days * 86400000);
        subscription = await prisma.subscription.create({
          data: { user_id: user.id, plan_id: plan.id, status: parsed.subscription_status ?? 'active', starts_at: startsAt, expires_at: expiresAt, created_at: new Date(), updated_at: new Date() },
          include: { plan: true },
        });
      }
    }

    return json(res, 201, {
      message: 'Aluno cadastrado com sucesso.',
      user: { ...serializeUser(user, subscription), roles: ['aluno'] },
    });
  }

  return json(res, 405, { message: 'Method not allowed' });
}

async function handleAdminUserDetail(req, res, userId) {
  if (req.method !== 'GET') return json(res, 405, { message: 'Method not allowed' });
  const auth = await requireAdmin(req, res);
  if (!auth) return;
  const user = await prisma.user.findUnique({
    where: { id: BigInt(userId) },
    include: {
      subscriptions: { orderBy: { created_at: 'desc' }, take: 1, include: { plan: true } },
      protocolAssignments: { where: { is_active: true }, orderBy: { assigned_at: 'desc' }, take: 1, include: { protocol: true } },
    },
  });
  if (!user) return json(res, 404, { message: 'Aluno não encontrado.' });
  const sub = user.subscriptions[0] ?? null;
  const assignment = user.protocolAssignments[0] ?? null;
  return json(res, 200, {
    user: serializeUser(user, sub),
    protocol_assignment: assignment
      ? { id: Number(assignment.id), notes: assignment.notes, assigned_at: assignment.assigned_at, protocol: { id: Number(assignment.protocol.id), title: assignment.protocol.title, type: assignment.protocol.type, content: assignment.protocol.content } }
      : null,
  });
}

async function handleToggleActive(req, res, userId) {
  if (req.method !== 'PATCH') return json(res, 405, { message: 'Method not allowed' });
  const auth = await requireAdmin(req, res);
  if (!auth) return;
  const user = await prisma.user.findUnique({ where: { id: BigInt(userId) } });
  if (!user) return json(res, 404, { message: 'Aluno não encontrado.' });
  const updated = await prisma.user.update({ where: { id: BigInt(userId) }, data: { is_active: !user.is_active } });
  return json(res, 200, { message: updated.is_active ? 'Aluno ativado.' : 'Aluno desativado.', is_active: updated.is_active });
}

async function handleUserProtocol(req, res, userId) {
  const auth = await requireAdmin(req, res);
  if (!auth) return;
  const studentId = BigInt(userId);

  if (req.method === 'GET') {
    const a = await prisma.protocolAssignment.findFirst({ where: { student_id: studentId, is_active: true }, orderBy: { assigned_at: 'desc' }, include: { protocol: true } });
    if (!a) return json(res, 200, { assignment: null });
    return json(res, 200, { assignment: { id: Number(a.id), notes: a.notes, assigned_at: a.assigned_at, protocol: { id: Number(a.protocol.id), title: a.protocol.title, type: a.protocol.type, content: a.protocol.content } } });
  }

  if (req.method === 'POST') {
    const schema = z.object({ protocol_id: z.number().int().positive(), notes: z.string().max(1000).optional().default('') });
    let body;
    try { body = (await readJsonBody(req)) ?? {}; } catch { return json(res, 400, { message: 'JSON inválido.' }); }
    let parsed;
    try { parsed = schema.parse(body); } catch (e) { return json(res, 422, { message: 'Dados inválidos.', errors: e?.errors ?? [] }); }

    const student = await prisma.user.findUnique({ where: { id: studentId } });
    if (!student) return json(res, 404, { message: 'Aluno não encontrado.' });
    const protocol = await prisma.protocol.findUnique({ where: { id: BigInt(parsed.protocol_id) } });
    if (!protocol) return json(res, 404, { message: 'Protocolo não encontrado.' });

    await prisma.protocolAssignment.updateMany({ where: { student_id: studentId, is_active: true }, data: { is_active: false } });
    const assignment = await prisma.protocolAssignment.create({
      data: { protocol_id: BigInt(parsed.protocol_id), student_id: studentId, assigned_by: auth.userId, notes: parsed.notes, is_active: true },
    });
    return json(res, 201, { message: 'Protocolo atribuído com sucesso.', assignment: { id: Number(assignment.id), protocol_id: Number(assignment.protocol_id), notes: assignment.notes, assigned_at: assignment.assigned_at } });
  }

  return json(res, 405, { message: 'Method not allowed' });
}

async function handleProtocolos(req, res) {
  const auth = await requireAdmin(req, res);
  if (!auth) return;

  if (req.method === 'GET') {
    const protocols = await prisma.protocol.findMany({ orderBy: { created_at: 'desc' } });
    return json(res, 200, protocols.map(serializeProtocol));
  }

  if (req.method === 'POST') {
    const schema = z.object({
      title: z.string().trim().min(2).max(255),
      type: z.enum(['treino', 'dieta', 'full']).default('full'),
      content: z.object({ treino: z.any().optional().default({}), dieta: z.any().optional().default({}) }),
      is_template: z.boolean().default(true),
    });
    let body;
    try { body = (await readJsonBody(req)) ?? {}; } catch { return json(res, 400, { message: 'JSON inválido.' }); }
    let parsed;
    try { parsed = schema.parse(body); } catch (e) { return json(res, 422, { message: 'Dados inválidos.', errors: e?.errors ?? [] }); }
    const protocol = await prisma.protocol.create({ data: { ...parsed, created_by: auth.userId, created_at: new Date(), updated_at: new Date() } });
    return json(res, 201, serializeProtocol(protocol));
  }

  return json(res, 405, { message: 'Method not allowed' });
}

async function handleProtocolDetail(req, res, protocolId) {
  const auth = await requireAdmin(req, res);
  if (!auth) return;
  const protocol = await prisma.protocol.findUnique({ where: { id: BigInt(protocolId) } });
  if (!protocol) return json(res, 404, { message: 'Protocolo não encontrado.' });

  if (req.method === 'GET') return json(res, 200, serializeProtocol(protocol));

  if (req.method === 'PUT') {
    const schema = z.object({ title: z.string().trim().min(2).max(255).optional(), type: z.enum(['treino', 'dieta', 'full']).optional(), content: z.object({ treino: z.any().optional(), dieta: z.any().optional() }).optional(), is_template: z.boolean().optional() });
    let body;
    try { body = (await readJsonBody(req)) ?? {}; } catch { return json(res, 400, { message: 'JSON inválido.' }); }
    let parsed;
    try { parsed = schema.parse(body); } catch (e) { return json(res, 422, { message: 'Dados inválidos.', errors: e?.errors ?? [] }); }
    const updated = await prisma.protocol.update({ where: { id: BigInt(protocolId) }, data: { ...parsed, updated_at: new Date() } });
    return json(res, 200, serializeProtocol(updated));
  }

  if (req.method === 'DELETE') {
    await prisma.protocolAssignment.updateMany({ where: { protocol_id: BigInt(protocolId) }, data: { is_active: false } });
    await prisma.protocol.delete({ where: { id: BigInt(protocolId) } });
    return json(res, 200, { message: 'Protocolo removido.' });
  }

  return json(res, 405, { message: 'Method not allowed' });
}

async function handleAdminPlanos(req, res, query) {
  if (req.method === 'GET') {
    const plans = await prisma.plan.findMany({ where: query.all === '1' ? {} : { is_active: true }, orderBy: { price: 'asc' } });
    return json(res, 200, plans.map(serializePlan));
  }
  const auth = await requireAdmin(req, res);
  if (!auth) return;

  if (req.method === 'POST') {
    const schema = z.object({
      name: z.string().trim().min(2).max(100),
      slug: z.string().trim().min(2).max(100).regex(/^[a-z0-9-]+$/),
      description: z.string().max(1000).optional().default(''),
      price: z.number().positive(),
      duration_days: z.number().int().positive().default(30),
      features: z.array(z.string()).default([]),
      is_active: z.boolean().default(true),
    });
    let body;
    try { body = (await readJsonBody(req)) ?? {}; } catch { return json(res, 400, { message: 'JSON inválido.' }); }
    let parsed;
    try { parsed = schema.parse(body); } catch (e) { return json(res, 422, { message: 'Dados inválidos.', errors: e?.errors ?? [] }); }
    const existing = await prisma.plan.findUnique({ where: { slug: parsed.slug } });
    if (existing) return json(res, 409, { message: 'Slug já em uso.' });
    const plan = await prisma.plan.create({ data: parsed });
    return json(res, 201, serializePlan(plan));
  }

  return json(res, 405, { message: 'Method not allowed' });
}

async function handleAdminPlanoDetail(req, res, planId) {
  const auth = await requireAdmin(req, res);
  if (!auth) return;
  const plan = await prisma.plan.findUnique({ where: { id: BigInt(planId) } });
  if (!plan) return json(res, 404, { message: 'Plano não encontrado.' });

  if (req.method === 'GET') return json(res, 200, serializePlan(plan));

  if (req.method === 'PUT') {
    const schema = z.object({ name: z.string().trim().min(2).max(100).optional(), description: z.string().max(1000).optional(), price: z.number().positive().optional(), duration_days: z.number().int().positive().optional(), features: z.array(z.string()).optional(), is_active: z.boolean().optional() });
    let body;
    try { body = (await readJsonBody(req)) ?? {}; } catch { return json(res, 400, { message: 'JSON inválido.' }); }
    let parsed;
    try { parsed = schema.parse(body); } catch (e) { return json(res, 422, { message: 'Dados inválidos.', errors: e?.errors ?? [] }); }
    const updated = await prisma.plan.update({ where: { id: BigInt(planId) }, data: parsed });
    return json(res, 200, serializePlan(updated));
  }

  if (req.method === 'DELETE') {
    await prisma.plan.update({ where: { id: BigInt(planId) }, data: { is_active: false } });
    return json(res, 200, { message: 'Plano desativado.' });
  }

  return json(res, 405, { message: 'Method not allowed' });
}

async function handlePublicPlans(req, res) {
  if (req.method !== 'GET') return json(res, 405, { message: 'Method not allowed' });
  const raw = await prisma.plan.findMany({
    where: { is_active: true },
    orderBy: { price: 'asc' },
    select: { id: true, name: true, slug: true, description: true, price: true, duration_days: true, features: true, is_active: true },
  });
  return json(res, 200, raw.map(serializePlan));
}

async function handleAlunoProtocolo(req, res) {
  if (req.method !== 'GET') return json(res, 405, { message: 'Method not allowed' });
  const auth = await requireAuth(req, res);
  if (!auth) return;
  const a = await prisma.protocolAssignment.findFirst({ where: { student_id: auth.userId, is_active: true }, orderBy: { assigned_at: 'desc' }, include: { protocol: true } });
  if (!a) return json(res, 200, { assignment: null });
  return json(res, 200, { assignment: { id: Number(a.id), notes: a.notes, assigned_at: a.assigned_at, protocol: { id: Number(a.protocol.id), title: a.protocol.title, type: a.protocol.type, content: a.protocol.content } } });
}

async function handleStudentInfinitePayCheckout(req, res) {
  if (req.method !== 'POST') return json(res, 405, { message: 'Method not allowed' });
  const auth = await requireAuth(req, res);
  if (!auth) return;

  const handle = env('INFINITEPAY_HANDLE');
  const redirectUrlBase = env('INFINITEPAY_REDIRECT_URL');
  const webhookUrl = env('INFINITEPAY_WEBHOOK_URL');
  if (!handle) return json(res, 500, { message: 'Missing INFINITEPAY_HANDLE' });
  if (!redirectUrlBase) return json(res, 500, { message: 'Missing INFINITEPAY_REDIRECT_URL' });
  if (!webhookUrl) return json(res, 500, { message: 'Missing INFINITEPAY_WEBHOOK_URL' });

  const bodySchema = z.object({ plan_id: z.number().int().positive() });
  let body;
  try { body = (await readJsonBody(req)) ?? {}; } catch { return json(res, 400, { message: 'Invalid JSON.' }); }
  let parsed;
  try { parsed = bodySchema.parse(body); } catch (e) { return json(res, 422, { message: 'Validation error', errors: e?.errors ?? [] }); }

  const plan = await prisma.plan.findUnique({ where: { id: BigInt(parsed.plan_id) } });
  if (!plan || !plan.is_active) return json(res, 404, { message: 'Plano não encontrado.' });

  const now = new Date();
  const orderNsu = makeOrderNsu();
  const amountCents = Math.max(0, Math.round(parseFloat(plan.price.toString()) * 100));

  const subscription = await prisma.subscription.create({
    data: {
      user_id: auth.userId,
      plan_id: plan.id,
      status: 'pending',
      starts_at: null,
      expires_at: null,
      created_at: now,
      updated_at: now,
    },
  });

  await prisma.transaction.create({
    data: {
      user_id: auth.userId,
      subscription_id: subscription.id,
      amount: plan.price,
      status: 'pending',
      payment_method: 'infinitepay',
      external_id: orderNsu,
      created_at: now,
      updated_at: now,
    },
  });

  const redirectUrl = new URL(redirectUrlBase);
  redirectUrl.searchParams.set('order_nsu', orderNsu);
  redirectUrl.searchParams.set('plan_id', String(parsed.plan_id));

  const payload = {
    handle,
    redirect_url: redirectUrl.toString(),
    webhook_url: webhookUrl,
    order_nsu: orderNsu,
    items: [{ quantity: 1, price: amountCents, description: plan.name }],
  };

  let linkResp;
  try {
    linkResp = await fetchJson('https://api.infinitepay.io/invoices/public/checkout/links', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  } catch (e) {
    console.error('InfinitePay link error', e?.status, e?.data ?? e);
    return json(res, 502, { message: 'Falha ao gerar link de pagamento.' });
  }

  return json(res, 200, {
    checkout_url: linkResp?.url ?? null,
    order_nsu: orderNsu,
  });
}

async function handleInfinitePayWebhook(req, res) {
  if (req.method !== 'POST') return json(res, 405, { message: 'Method not allowed' });

  let body;
  try { body = (await readJsonBody(req)) ?? {}; } catch { return json(res, 400, { message: 'Invalid JSON.' }); }

  const schema = z.object({
    order_nsu: z.string().min(1),
    transaction_nsu: z.string().min(1),
    invoice_slug: z.string().min(1),
    amount: z.number().optional(),
    paid_amount: z.number().optional(),
    installments: z.number().optional(),
    capture_method: z.string().optional(),
    receipt_url: z.string().optional(),
  });

  let parsed;
  try { parsed = schema.parse(body); } catch (e) { return json(res, 422, { message: 'Validation error', errors: e?.errors ?? [] }); }

  const tx = await prisma.transaction.findFirst({
    where: { external_id: parsed.order_nsu, payment_method: 'infinitepay' },
  });

  if (!tx) {
    return json(res, 200, { received: true });
  }

  if (tx.status === 'paid') {
    return json(res, 200, { received: true });
  }

  const now = new Date();

  await prisma.transaction.update({
    where: { id: tx.id },
    data: {
      status: 'paid',
      paid_at: now,
      updated_at: now,
    },
  });

  if (tx.subscription_id) {
    const sub = await prisma.subscription.findUnique({ where: { id: tx.subscription_id } });
    if (sub) {
      const plan = await prisma.plan.findUnique({ where: { id: sub.plan_id } });
      const startsAt = now;
      const expiresAt = plan
        ? new Date(startsAt.getTime() + (plan.duration_days ?? 30) * 86400000)
        : null;
      await prisma.subscription.update({
        where: { id: sub.id },
        data: {
          status: 'active',
          starts_at: startsAt,
          expires_at: expiresAt,
          updated_at: now,
        },
      });
    }
  }

  return json(res, 200, { received: true });
}

// ── Main Router ──────────────────────────────────────────────────────────────

export default async function handler(req, res) {
  applyCors(req, res);
  if (req.method === 'OPTIONS') return json(res, 204, null);

  const urlObj = new URL(req.url, 'http://localhost');
  const query = Object.fromEntries(urlObj.searchParams.entries());
  const p = urlObj.pathname.replace(/^\/api/, '');
  const segs = p.split('/').filter(Boolean);

  try {
    if (segs[0] === 'auth' && segs[1] === 'login')   return await handleLogin(req, res);
    if (segs[0] === 'auth' && segs[1] === 'me')      return await handleMe(req, res);
    if (segs[0] === 'auth' && segs[1] === 'logout')  return json(res, 200, { message: 'Logout realizado com sucesso.' });
    if (segs[0] === 'auth' && segs[1] === 'refresh') return json(res, 200, { message: 'Token válido.' });

    if (segs[0] === 'plans' && !segs[1])             return await handlePublicPlans(req, res);
    if (segs[0] === 'health')                         return json(res, 200, { ok: true });

    if (segs[0] === 'aluno' && segs[1] === 'protocolo') return await handleAlunoProtocolo(req, res);
    if (segs[0] === 'student' && segs[1] === 'checkout' && segs[2] === 'infinitepay') return await handleStudentInfinitePayCheckout(req, res);

    if (segs[0] === 'webhooks' && segs[1] === 'infinitepay') return await handleInfinitePayWebhook(req, res);

    if (segs[0] === 'admin' && segs[1] === 'dashboard') return await handleDashboard(req, res);

    if (segs[0] === 'admin' && segs[1] === 'users') {
      if (!segs[2])                        return await handleAdminUsers(req, res, query);
      if (segs[3] === 'toggle-active')     return await handleToggleActive(req, res, segs[2]);
      if (segs[3] === 'protocolo')         return await handleUserProtocol(req, res, segs[2]);
      return await handleAdminUserDetail(req, res, segs[2]);
    }

    if (segs[0] === 'admin' && segs[1] === 'protocolos') {
      if (segs[2]) return await handleProtocolDetail(req, res, segs[2]);
      return await handleProtocolos(req, res);
    }

    if (segs[0] === 'admin' && segs[1] === 'planos') {
      if (segs[2]) return await handleAdminPlanoDetail(req, res, segs[2]);
      return await handleAdminPlanos(req, res, query);
    }

    return json(res, 404, { message: 'Not found.' });
  } catch (err) {
    console.error(err);
    return json(res, 500, { message: 'Internal server error.' });
  }
}
