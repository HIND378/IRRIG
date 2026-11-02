import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { messageErreur } from '../api';
import { ACCUEIL, useAuth } from '../auth';
import { Message } from '../components/ui';

/** Comptes créés par le seeder (mot de passe : password). */
const COMPTES = [
  ['Agriculteur', 'agriculteur@irrig.ma'],
  ['Commercial', 'commercial.agadir@irrig.ma'],
  ['Responsable', 'responsable.agadir@irrig.ma'],
  ['Directeur', 'directeur@irrig.ma'],
  ['Technicien', 'technicien1.agadir@irrig.ma'],
  ['Magasinier', 'magasinier.agadir@irrig.ma'],
  ['Admin', 'admin@irrig.ma'],
];

export default function Login() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [erreur, setErreur] = useState('');
  const [chargement, setChargement] = useState(false);

  async function envoyer(e) {
    e.preventDefault();
    setErreur('');
    setChargement(true);
    try {
      const user = await login(email, password);
      navigate(ACCUEIL[user.role]);
    } catch (err) {
      setErreur(messageErreur(err));
    } finally {
      setChargement(false);
    }
  }

  return (
    <div className="page-login">
      <div className="visuel">
        <h1>IRRIG Connect</h1>
        <p>
          La plateforme unique d'IRRIG : clients, parc installé, interventions, stock et tableaux de bord pour les agences
          d'Agadir, Rabat et Casablanca.
        </p>
      </div>
      <form onSubmit={envoyer}>
        <Link to="/" className="lien-retour">← Retour à l'accueil</Link>
        <h2>Connexion</h2>
        <Message erreur={erreur} />
        <div className="champ">
          <label htmlFor="email">Adresse e-mail</label>
          <input id="email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoFocus />
        </div>
        <div className="champ">
          <label htmlFor="mdp">Mot de passe</label>
          <input id="mdp" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
        </div>
        <button type="submit" disabled={chargement} style={{ width: '100%', justifyContent: 'center' }}>
          {chargement ? 'Connexion…' : 'Se connecter'}
        </button>

        <div className="comptes-demo">
          Comptes de démonstration (mot de passe <code>password</code>) :
          <div>
            {COMPTES.map(([role, mail]) => (
              <button
                type="button"
                key={mail}
                className="secondaire petit"
                onClick={() => {
                  setEmail(mail);
                  setPassword('password');
                }}
              >
                {role}
              </button>
            ))}
          </div>
        </div>
      </form>
    </div>
  );
}
