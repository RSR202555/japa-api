import { applyCors, json, methodNotAllowed } from '../_lib/response.js';

// JWT é stateless — o cliente descarta o token.
// Este endpoint existe para manter compatibilidade com o frontend.
export default async function handler(req, res) {
  applyCors(req, res);
  if (req.method === 'OPTIONS') return json(res, 204, null);
  if (req.method !== 'POST') return methodNotAllowed(res);

  return json(res, 200, { message: 'Logout realizado com sucesso.' });
}
