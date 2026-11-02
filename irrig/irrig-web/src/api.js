import axios from 'axios';

/**
 * Client HTTP unique vers l'API REST Laravel.
 * Le jeton Sanctum est ajouté à chaque requête (en-tête Authorization: Bearer).
 */
export const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

const api = axios.create({
  baseURL: API_URL,
  headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('irrig_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401 && !err.config.url.endsWith('/login')) {
      localStorage.removeItem('irrig_token');
      localStorage.removeItem('irrig_user');
      window.location.href = '/login';
    }
    return Promise.reject(err);
  },
);

/** Message d'erreur lisible à partir d'une réponse Laravel (validation 422, 403...). */
export function messageErreur(err) {
  const data = err?.response?.data;
  if (data?.errors) return Object.values(data.errors).flat().join(' ');
  if (data?.message) return data.message;
  return "Impossible de joindre le serveur. Vérifiez que l'API est démarrée.";
}

export default api;
