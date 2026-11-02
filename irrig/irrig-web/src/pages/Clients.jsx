import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api, { messageErreur } from '../api';
import { useAuth } from '../auth';
import { Message, Modal, libelle } from '../components/ui';

const VIDE = { nom: '', type: 'petit_exploitant', telephone: '', email: '', langue: 'ar', agence_id: '' };

export default function Clients() {
  const { user } = useAuth();
  const [clients, setClients] = useState([]);
  const [agences, setAgences] = useState([]);
  const [q, setQ] = useState('');
  const [formulaire, setFormulaire] = useState(null);
  const [erreur, setErreur] = useState('');

  const multiAgences = user.role === 'directeur';

  async function charger() {
    try {
      const { data } = await api.get('/clients', { params: { q, par_page: 100 } });
      setClients(data.data);
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  useEffect(() => {
    const t = setTimeout(charger, 250); // recherche pendant la saisie
    return () => clearTimeout(t);
  }, [q]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (multiAgences) api.get('/agences').then(({ data }) => setAgences(data));
  }, [multiAgences]);

  async function enregistrer(e) {
    e.preventDefault();
    try {
      await api.post('/clients', { ...formulaire, agence_id: formulaire.agence_id || undefined });
      setFormulaire(null);
      charger();
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  return (
    <>
      <div className="entete">
        <div>
          <h1>Clients et exploitations</h1>
          <p className="sous-titre">Base clients unique, partagée par les agences.</p>
        </div>
        <button onClick={() => { setErreur(''); setFormulaire(VIDE); }}>+ Nouveau client</button>
      </div>
      {!formulaire && <Message erreur={erreur} />}

      <div className="carte">
        <div className="filtres">
          <input placeholder="Rechercher par nom ou téléphone…" value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Client</th>
                <th>Type</th>
                <th>Téléphone</th>
                <th>Agence</th>
                <th className="num">Exploitations</th>
              </tr>
            </thead>
            <tbody>
              {clients.map((c) => (
                <tr key={c.id}>
                  <td><Link to={`/clients/${c.id}`}>{c.nom}</Link></td>
                  <td>{libelle(c.type)}</td>
                  <td>{c.telephone}</td>
                  <td>{c.agence?.ville}</td>
                  <td className="num">{c.exploitations_count}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {clients.length === 0 && <div className="vide">Aucun client trouvé.</div>}
        </div>
      </div>

      {formulaire && (
        <Modal titre="Nouveau client" onFermer={() => setFormulaire(null)}>
          <form onSubmit={enregistrer}>
            <Message erreur={erreur} />
            <div className="champ">
              <label>Nom ou raison sociale</label>
              <input value={formulaire.nom} onChange={(e) => setFormulaire({ ...formulaire, nom: e.target.value })} required />
            </div>
            <div className="ligne-champs">
              <div className="champ">
                <label>Type</label>
                <select value={formulaire.type} onChange={(e) => setFormulaire({ ...formulaire, type: e.target.value })}>
                  <option value="petit_exploitant">Petit exploitant</option>
                  <option value="cooperative">Coopérative</option>
                  <option value="entreprise">Entreprise agricole</option>
                </select>
              </div>
              <div className="champ">
                <label>Langue préférée</label>
                <select value={formulaire.langue} onChange={(e) => setFormulaire({ ...formulaire, langue: e.target.value })}>
                  <option value="ar">Arabe</option>
                  <option value="fr">Français</option>
                </select>
              </div>
            </div>
            <div className="ligne-champs">
              <div className="champ">
                <label>Téléphone</label>
                <input placeholder="06 12 34 56 78" value={formulaire.telephone} onChange={(e) => setFormulaire({ ...formulaire, telephone: e.target.value })} required />
              </div>
              <div className="champ">
                <label>E-mail (facultatif)</label>
                <input type="email" value={formulaire.email} onChange={(e) => setFormulaire({ ...formulaire, email: e.target.value })} />
              </div>
            </div>
            {multiAgences && (
              <div className="champ">
                <label>Agence</label>
                <select value={formulaire.agence_id} onChange={(e) => setFormulaire({ ...formulaire, agence_id: e.target.value })} required>
                  <option value="">Choisir…</option>
                  {agences.map((a) => <option key={a.id} value={a.id}>{a.ville}</option>)}
                </select>
              </div>
            )}
            <div className="actions">
              <button type="button" className="secondaire" onClick={() => setFormulaire(null)}>Annuler</button>
              <button type="submit">Enregistrer</button>
            </div>
          </form>
        </Modal>
      )}
    </>
  );
}
