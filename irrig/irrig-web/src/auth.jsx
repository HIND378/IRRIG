import { createContext, useContext, useState } from 'react';
import api from './api';

const AuthContext = createContext(null);

function lireUtilisateur() {
  try {
    return JSON.parse(localStorage.getItem('irrig_user'));
  } catch {
    return null;
  }
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(lireUtilisateur);

  async function login(email, password) {
    const { data } = await api.post('/login', { email, password, appareil: 'web' });
    localStorage.setItem('irrig_token', data.token);
    localStorage.setItem('irrig_user', JSON.stringify(data.user));
    setUser(data.user);
    return data.user;
  }

  async function logout() {
    try {
      await api.post('/logout');
    } finally {
      localStorage.removeItem('irrig_token');
      localStorage.removeItem('irrig_user');
      setUser(null);
    }
  }

  return <AuthContext.Provider value={{ user, login, logout }}>{children}</AuthContext.Provider>;
}

export const useAuth = () => useContext(AuthContext);

/** Libellés des 7 rôles du diagramme de cas d'utilisation. */
export const ROLES = {
  agriculteur: 'Agriculteur',
  commercial: 'Commercial',
  responsable: "Responsable d'agence",
  directeur: 'Directeur multi-agences',
  technicien: 'Technicien',
  magasinier: 'Magasinier',
  admin: 'Administrateur',
};

/** Page d'accueil de chaque rôle après connexion. */
export const ACCUEIL = {
  agriculteur: '/portail',
  commercial: '/clients',
  responsable: '/interventions',
  directeur: '/tableau-de-bord/consolide',
  technicien: '/technicien',
  magasinier: '/stock',
  admin: '/utilisateurs',
};
