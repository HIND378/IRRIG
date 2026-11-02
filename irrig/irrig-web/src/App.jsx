import { Navigate, Route, Routes } from 'react-router-dom';
import { ACCUEIL, useAuth } from './auth';
import Layout from './components/Layout';
import Accueil from './pages/Accueil';
import Login from './pages/Login';
import Clients from './pages/Clients';
import ClientFiche from './pages/ClientFiche';
import Devis from './pages/Devis';
import Interventions from './pages/Interventions';
import Planning from './pages/Planning';
import TableauDeBord from './pages/TableauDeBord';
import TableauConsolide from './pages/TableauConsolide';
import Stock from './pages/Stock';
import Transferts from './pages/Transferts';
import Agences from './pages/Agences';
import Utilisateurs from './pages/Utilisateurs';
import MissionsTechnicien from './pages/MissionsTechnicien';
import Portail from './portail/Portail';

/** Protège une route : connecté + rôle autorisé (le directeur hérite du responsable). */
function Protege({ roles, children }) {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  const autorises = roles.includes('responsable') ? [...roles, 'directeur'] : roles;
  if (!autorises.includes(user.role)) return <Navigate to={ACCUEIL[user.role]} replace />;
  return children;
}

export default function App() {
  const { user } = useAuth();

  return (
    <Routes>
      {/* Page d'accueil publique : présentation de l'entreprise, bouton Connexion en haut à droite */}
      <Route path="/" element={<Accueil />} />
      <Route path="/login" element={user ? <Navigate to={ACCUEIL[user.role]} replace /> : <Login />} />

      {/* Portail client : interface à part, très simple, arabe / français */}
      <Route path="/portail" element={<Protege roles={['agriculteur']}><Portail /></Protege>} />

      <Route element={<Protege roles={['commercial', 'responsable', 'technicien', 'magasinier', 'admin']}><Layout /></Protege>}>
        <Route path="/clients" element={<Protege roles={['commercial', 'responsable']}><Clients /></Protege>} />
        <Route path="/clients/:id" element={<Protege roles={['commercial', 'responsable']}><ClientFiche /></Protege>} />
        <Route path="/devis" element={<Protege roles={['commercial', 'responsable']}><Devis /></Protege>} />
        <Route path="/interventions" element={<Protege roles={['commercial', 'responsable']}><Interventions /></Protege>} />
        <Route path="/planning" element={<Protege roles={['responsable']}><Planning /></Protege>} />
        <Route path="/tableau-de-bord" element={<Protege roles={['responsable']}><TableauDeBord /></Protege>} />
        <Route path="/tableau-de-bord/consolide" element={<Protege roles={['directeur']}><TableauConsolide /></Protege>} />
        <Route path="/stock" element={<Protege roles={['magasinier', 'responsable']}><Stock /></Protege>} />
        <Route path="/transferts" element={<Protege roles={['magasinier', 'directeur']}><Transferts /></Protege>} />
        <Route path="/agences" element={<Protege roles={['directeur']}><Agences /></Protege>} />
        <Route path="/utilisateurs" element={<Protege roles={['admin']}><Utilisateurs /></Protege>} />
        <Route path="/technicien" element={<Protege roles={['technicien']}><MissionsTechnicien /></Protege>} />
      </Route>

      <Route path="*" element={<Navigate to={user ? ACCUEIL[user.role] : '/'} replace />} />
    </Routes>
  );
}
