import { prisma } from '../_lib/prisma.js';
import { applyCors, json, methodNotAllowed } from '../_lib/response.js';
import { getBearerToken, verifyAccessToken } from '../_lib/auth.js';

export default async function handler(req, res) {
  applyCors(req, res);
  if (req.method === 'OPTIONS') return json(res, 204, null);
  if (req.method !== 'GET') return methodNotAllowed(res);

  const token = getBearerToken(req);
  if (!token) {
    return json(res, 401, { message: 'Unauthenticated.' });
  }

  let decoded;
  try {
    decoded = verifyAccessToken(token);
  } catch {
    return json(res, 401, { message: 'Invalid token.' });
  }

  const userId = BigInt(decoded.sub);

  const user = await prisma.user.findUnique({
    where: { id: userId },
    select: {
      id: true, name: true, email: true, email_verified_at: true,
      phone: true, date_of_birth: true, avatar_url: true,
      is_active: true, created_at: true,
    },
  });

  if (!user) {
    return json(res, 404, { message: 'Usuário não encontrado.' });
  }

  // Roles do usuário (relação polimórfica via model_has_roles)
  const roleAssignments = await prisma.modelHasRole.findMany({
    where: { model_id: userId, model_type: 'App\\Models\\User' },
    include: { role: true },
  });

  return json(res, 200, {
    user: {
      ...user,
      id: Number(user.id), // BigInt → Number
      roles: roleAssignments.map((r) => r.role.name),
      email_verified: user.email_verified_at !== null,
      subscription: null,
    },
  });
}
