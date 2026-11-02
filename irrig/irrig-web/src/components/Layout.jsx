import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { ROLES, useAuth } from '../auth';

/** Menu de chaque rôle = ses cas d'utilisation (diagramme de la séance 1). */
const MENUS = {
  commercial: [
    ['/clients', 'Clients et exploitations'],
    ['/devis', 'Devis et commandes'],
    ['/interventions', 'Interventions'],
  ],
  responsable: [
    ['/interventions', 'Demandes d’intervention'],
    ['/planning', 'Planning'],
    ['/tableau-de-bord', 'Tableau de bord agence'],
    ['/clients', 'Clients et archives'],
    ['/devis', 'Devis'],
    ['/stock', 'Stock'],
  ],
  directeur: [
    ['/tableau-de-bord/consolide', 'Tableau de bord consolidé'],
    ['/tableau-de-bord', 'Tableau de bord par agence'],
    ['/transferts', 'Transferts de stock'],
    ['/agences', 'Agences'],
    ['/interventions', 'Interventions'],
    ['/planning', 'Planning'],
    ['/stock', 'Stock'],
    ['/clients', 'Clients'],
  ],
  technicien: [['/technicien', 'Mes missions']],
  magasinier: [
    ['/stock', 'Stock de l’agence'],
    ['/transferts', 'Transferts'],
  ],
  admin: [['/utilisateurs', 'Utilisateurs et rôles']],
};

export default function Layout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  async function deconnexion() {
    await logout();
    navigate('/');
  }

  return (
    <div className="app">
      <nav className="menu">
        <div className="logo">
          <span aria-hidden="true">💧</span> IRRIG Connect
        </div>
        {(MENUS[user.role] || []).map(([chemin, texte]) => (
          <NavLink key={chemin} to={chemin} end>
            {texte}
          </NavLink>
        ))}
        <div className="profil">
          <strong>{user.name}</strong>
          {ROLES[user.role]}
          {user.agence ? ` · ${user.agence.ville}` : ''}
          <div style={{ marginTop: 8 }}>
            <button className="secondaire petit" onClick={deconnexion}>
              Se déconnecter
            </button>
          </div>
        </div>
      </nav>
      <main className="contenu">
        <Outlet />
      </main>
    </div>
  );
}
