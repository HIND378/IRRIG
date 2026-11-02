import { useEffect, useState } from 'react';
import api, { messageErreur } from '../api';
import { ROLES } from '../auth';
import { Badge, Message, Modal } from '../components/ui';

const ROLES_AGENCE = ['commercial', 'responsable', 'technicien', 'magasinier'];
const VIDE = { name: '', email: '', telephone: '', role: 'commercial', agence_id: '', client_id: '', specialite: '', zone: '', password: '' };

/** Gestion des comptes et des rôles (administrateur). */
export default function Utilisateurs() {
  const [users, setUsers] = useState([]);
  const [agences, setAgences] = useState([]);
  const [clients, setClients] = useState([]);
  const [filtre, setFiltre] = useState('');
  const [form, setForm] = useState(null);
  const [erreur, setErreur] = useState('');

  const charger = () =>
    api.get('/users', { params: { role: filtre || undefined } }).then(({ data }) => setUsers(data)).catch((e) => setErreur(messageErreur(e)));

  useEffect(() => {
    charger();
  }, [filtre]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    api.get('/agences').then(({ data }) => setAgences(data));
    api.get('/clients', { params: { par_page: 500 } }).then(({ data }) => setClients(data.data));
  }, []);

  async function enregistrer(e) {
    e.preventDefault();
    const donnees = { ...form };
    ['agence_id', 'client_id'].forEach((c) => { if (!donnees[c]) donnees[c] = null; });
    try {
      if (form.id) await api.put(`/users/${form.id}`, donnees);
      else await api.post('/users', donnees);
      setForm(null);
      charger();
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  async function basculer(u) {
    try {
      await api.put(`/users/${u.id}`, { actif: !u.actif });
      charger();
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  const ouvrir = (u) => { setErreur(''); setForm(u ? { ...VIDE, ...u, password: '', agence_id: u.agence_id ?? '', client_id: u.client_id ?? '' } : VIDE); };

  return (
    <>
      <div className="entete">
        <div>
          <h1>Utilisateurs et rôles</h1>
          <p className="sous-titre">Chaque compte n'accède qu'aux fonctions de son rôle.</p>
        </div>
        <button onClick={() => ouvrir(null)}>+ Nouveau compte</button>
      </div>
      {!form && <Message erreur={erreur} />}

      <div className="carte">
        <div className="filtres">
          <select value={filtre} onChange={(e) => setFiltre(e.target.value)}>
            <option value="">Tous les rôles</option>
            {Object.entries(ROLES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </select>
        </div>
        <div className="table-scroll">
          <table>
            <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Agence / client</th><th>État</th><th></th></tr></thead>
            <tbody>
              {users.map((u) => (
                <tr key={u.id}>
                  <td>{u.name}</td>
                  <td>{u.email}</td>
                  <td>{ROLES[u.role]}</td>
                  <td>{u.agence?.ville || u.client?.nom || (['directeur', 'admin'].includes(u.role) ? 'Toutes les agences' : '—')}</td>
                  <td>{u.actif ? <Badge valeur="ok" texte="Actif" /> : <Badge valeur="alerte" texte="Désactivé" />}</td>
                  <td>
                    <div className="actions">
                      <button className="secondaire petit" onClick={() => ouvrir(u)}>Modifier</button>
                      <button className="secondaire petit" onClick={() => basculer(u)}>{u.actif ? 'Désactiver' : 'Réactiver'}</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {form && (
        <Modal titre={form.id ? `Modifier ${form.name}` : 'Nouveau compte'} onFermer={() => setForm(null)}>
          <form onSubmit={enregistrer}>
            <Message erreur={erreur} />
            <div className="ligne-champs">
              <div className="champ"><label>Nom</label><input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required /></div>
              <div className="champ"><label>Téléphone</label><input value={form.telephone ?? ''} onChange={(e) => setForm({ ...form, telephone: e.target.value })} /></div>
            </div>
            <div className="champ"><label>E-mail (identifiant)</label><input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required /></div>
            <div className="ligne-champs">
              <div className="champ">
                <label>Rôle</label>
                <select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })}>
                  {Object.entries(ROLES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                </select>
              </div>
              {ROLES_AGENCE.includes(form.role) && (
                <div className="champ">
                  <label>Agence</label>
                  <select value={form.agence_id} onChange={(e) => setForm({ ...form, agence_id: e.target.value })} required>
                    <option value="">Choisir…</option>
                    {agences.map((a) => <option key={a.id} value={a.id}>{a.ville}</option>)}
                  </select>
                </div>
              )}
              {form.role === 'agriculteur' && (
                <div className="champ">
                  <label>Fiche client</label>
                  <select value={form.client_id} onChange={(e) => setForm({ ...form, client_id: e.target.value })} required>
                    <option value="">Choisir…</option>
                    {clients.map((c) => <option key={c.id} value={c.id}>{c.nom} — {c.telephone}</option>)}
                  </select>
                </div>
              )}
            </div>
            {form.role === 'technicien' && (
              <div className="ligne-champs">
                <div className="champ"><label>Spécialité</label><input value={form.specialite ?? ''} onChange={(e) => setForm({ ...form, specialite: e.target.value })} /></div>
                <div className="champ"><label>Zone</label><input value={form.zone ?? ''} onChange={(e) => setForm({ ...form, zone: e.target.value })} /></div>
              </div>
            )}
            <div className="champ">
              <label>{form.id ? 'Nouveau mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe (8 caractères minimum)'}</label>
              <input type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required={!form.id} minLength={8} />
            </div>
            <div className="actions">
              <button type="button" className="secondaire" onClick={() => setForm(null)}>Annuler</button>
              <button type="submit">Enregistrer</button>
            </div>
          </form>
        </Modal>
      )}
    </>
  );
}
