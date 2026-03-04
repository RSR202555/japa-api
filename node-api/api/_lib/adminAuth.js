import { getBearerToken, verifyAccessToken } from './auth.js';
import { prisma } from './prisma.js';
import { json } from './response.js';

/**
 * Verifica autenticação + role admin.
 * Retorna { userId } se OK, ou envia 401/403 e retorna null.
 */
export async function requireAdmin(req, res) {
  const token = getBearerToken(req);
  if (!token) {
    json(res, 401, { message: 'Não autorizado.' });
    return null;
  }

  let payload;
  try {
    payload = verifyAccessToken(token);
  } catch {
    json(res, 401, { message: 'Token inválido ou expirado.' });
    return null;
  }

  const userId = BigInt(payload.sub);

  const roleAssignment = await prisma.modelHasRole.findFirst({
    where: {
      model_id: userId,
      model_type: 'App\\Models\\User',
      role: { name: 'admin' },
    },
  });

  if (!roleAssignment) {
    json(res, 403, { message: 'Acesso negado.' });
    return null;
  }

  return { userId };
}

/**
 * Verifica apenas autenticação (sem role específica).
 * Retorna { userId } se OK, ou envia 401 e retorna null.
 */
export async function requireAuth(req, res) {
  const token = getBearerToken(req);
  if (!token) {
    json(res, 401, { message: 'Não autorizado.' });
    return null;
  }

  let payload;
  try {
    payload = verifyAccessToken(token);
  } catch {
    json(res, 401, { message: 'Token inválido ou expirado.' });
    return null;
  }

  return { userId: BigInt(payload.sub) };
}
