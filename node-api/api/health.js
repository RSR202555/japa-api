import { applyCors, json, methodNotAllowed } from './_lib/response.js';

export default async function handler(req, res) {
  applyCors(req, res);
  if (req.method === 'OPTIONS') return json(res, 204, null);
  if (req.method !== 'GET') return methodNotAllowed(res);
  return json(res, 200, { status: 'ok' });
}
