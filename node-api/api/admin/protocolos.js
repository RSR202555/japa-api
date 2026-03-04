import { applyCors, json, methodNotAllowed, readJsonBody } from '../_lib/response.js';
import { prisma } from '../_lib/prisma.js';
import { requireAdmin } from '../_lib/adminAuth.js';
import { z } from 'zod';

const protocolSchema = z.object({
  title: z.string().trim().min(2).max(255),
  type: z.enum(['treino', 'dieta', 'full']).default('full'),
  content: z.object({
    treino: z.object({
      objetivo: z.string().default(''),
      frequencia: z.string().default(''),
      observacoes: z.string().default(''),
      dias: z.array(z.object({
        id: z.string(),
        nome: z.string(),
        exercicios: z.array(z.object({
          id: z.string(),
          nome: z.string(),
          series: z.string().default(''),
          repeticoes: z.string().default(''),
          carga: z.string().default(''),
          descanso: z.string().default(''),
        })),
      })).default([]),
    }).optional().default({}),
    dieta: z.object({
      objetivo: z.string().default(''),
      calorias_totais: z.string().default(''),
      proteina_g: z.string().default(''),
      carboidrato_g: z.string().default(''),
      gordura_g: z.string().default(''),
      observacoes: z.string().default(''),
      refeicoes: z.array(z.object({
        id: z.string(),
        nome: z.string(),
        horario: z.string().default(''),
        alimentos: z.array(z.object({
          id: z.string(),
          nome: z.string(),
          quantidade: z.string().default(''),
        })),
      })).default([]),
    }).optional().default({}),
  }),
  is_template: z.boolean().default(true),
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

  // GET — listar protocolos
  if (req.method === 'GET') {
    const protocols = await prisma.protocol.findMany({
      orderBy: { created_at: 'desc' },
    });
    return json(res, 200, protocols.map(serializeProtocol));
  }

  // POST — criar protocolo
  if (req.method === 'POST') {
    let body;
    try {
      body = (await readJsonBody(req)) ?? {};
    } catch {
      return json(res, 400, { message: 'JSON inválido.' });
    }

    let parsed;
    try {
      parsed = protocolSchema.parse(body);
    } catch (e) {
      return json(res, 422, { message: 'Dados inválidos.', errors: e?.errors ?? [] });
    }

    const protocol = await prisma.protocol.create({
      data: {
        title: parsed.title,
        type: parsed.type,
        content: parsed.content,
        is_template: parsed.is_template,
        created_by: auth.userId,
        created_at: new Date(),
        updated_at: new Date(),
      },
    });

    return json(res, 201, serializeProtocol(protocol));
  }

  return methodNotAllowed(res);
}
