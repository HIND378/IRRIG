import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api, { messageErreur } from '../api';
import { useAuth } from '../auth';
import { Badge, Message, date, dh } from '../components/ui';
import { TEXTES } from './textes';

/**
 * Portail client de l'agriculteur : trois écrans seulement, grandes cibles,
 * arabe ou français. Cas d'utilisation : demander une intervention,
 * suivre ses demandes, évaluer l'intervention, consulter ses factures.
 */
export default function Portail() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [langue, setLangue] = useState(() => localStorage.getItem('irrig_langue') || user.client?.langue || 'ar');
  const [ecran, setEcran] = useState('nouvelle');
  const t = TEXTES[langue];

  useEffect(() => {
    localStorage.setItem('irrig_langue', langue);
    document.documentElement.lang = langue;
    return () => { document.documentElement.lang = 'fr'; };
  }, [langue]);

  return (
    <div className="portail" dir={langue === 'ar' ? 'rtl' : 'ltr'}>
      <div className="portail-entete">
        <div>
          <h1>💧 IRRIG</h1>
          <div>{t.bonjour} {user.name}</div>
        </div>
        <div className="actions">
          <button className="secondaire petit" onClick={() => setLangue(langue === 'ar' ? 'fr' : 'ar')}>
            {langue === 'ar' ? 'Français' : 'العربية'}
          </button>
          <button className="secondaire petit" onClick={async () => { await logout(); navigate('/'); }}>{t.deconnexion}</button>
        </div>
      </div>

      <div className="tuiles">
        {[['nouvelle', '🛠️'], ['suivi', '📋'], ['factures', '🧾']].map(([cle, icone]) => (
          <button key={cle} className={`tuile ${ecran === cle ? 'active' : ''}`} onClick={() => setEcran(cle)}>
            <span className="icone" aria-hidden="true">{icone}</span>
            {t[cle]}
          </button>
        ))}
      </div>

      {ecran === 'nouvelle' && <NouvelleDemande t={t} onEnvoyee={() => setEcran('suivi')} />}
      {ecran === 'suivi' && <MesDemandes t={t} />}
      {ecran === 'factures' && <MesFactures t={t} />}
    </div>
  );
}

function NouvelleDemande({ t, onEnvoyee }) {
  const [exploitations, setExploitations] = useState([]);
  const [form, setForm] = useState({ equipement_id: '', type: 'panne', description: '' });
  const [erreur, setErreur] = useState('');
  const [succes, setSucces] = useState('');

  useEffect(() => {
    api.get('/mes-equipements').then(({ data }) => setExploitations(data)).catch((e) => setErreur(messageErreur(e)));
  }, []);

  async function envoyer(e) {
    e.preventDefault();
    setErreur('');
    try {
      await api.post('/interventions', form);
      setSucces(t.envoyee);
      setForm({ equipement_id: '', type: 'panne', description: '' });
      setTimeout(onEnvoyee, 2500);
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  return (
    <form className="carte" onSubmit={envoyer}>
      <h2>{t.nouvelle}</h2>
      <Message erreur={erreur} succes={succes} />
      <div className="champ">
        <label>{t.equipement}</label>
        <select value={form.equipement_id} onChange={(e) => setForm({ ...form, equipement_id: e.target.value })} required>
          <option value="">{t.choisir}</option>
          {exploitations.map((ex) => (
            <optgroup key={ex.id} label={`${ex.nom || ''} ${ex.commune}`}>
              {ex.equipements.map((eq) => (
                <option key={eq.id} value={eq.id}>{t.equipements[eq.type]} — {eq.numero_serie}</option>
              ))}
            </optgroup>
          ))}
        </select>
      </div>
      <div className="champ">
        <label>{t.type}</label>
        <div className="actions">
          {['panne', 'maintenance', 'installation'].map((type) => (
            <button type="button" key={type} className={form.type === type ? '' : 'secondaire'} onClick={() => setForm({ ...form, type })}>
              {t[type]}
            </button>
          ))}
        </div>
      </div>
      <div className="champ">
        <label>{t.description}</label>
        <textarea rows="4" placeholder={t.placeholder} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} required />
      </div>
      <button type="submit" style={{ width: '100%', justifyContent: 'center', padding: 14, fontSize: '1.05rem' }}>{t.envoyer}</button>
    </form>
  );
}

function MesDemandes({ t }) {
  const [demandes, setDemandes] = useState([]);
  const [erreur, setErreur] = useState('');

  const charger = () => api.get('/interventions', { params: { par_page: 50 } }).then(({ data }) => setDemandes(data.data)).catch((e) => setErreur(messageErreur(e)));

  useEffect(() => {
    charger();
  }, []);

  return (
    <>
      <Message erreur={erreur} />
      {demandes.length === 0 && <div className="carte vide">{t.aucune}</div>}
      {demandes.map((d) => (
        <div className="carte" key={d.id}>
          <div className="entete">
            <h3>{t.equipements[d.equipement.type]} — n°{d.id}</h3>
            <Badge valeur={d.statut} texte={t.statuts[d.statut]} />
          </div>
          <p>{d.description}</p>
          {d.technicien && <p>{t.technicien} : {d.technicien.name} · {t.passage} {date(d.date_planifiee, true)}</p>}
          {d.statut === 'cloturee' && <p>{t.termine} {date(d.date_cloture)}</p>}
          {d.statut === 'cloturee' && !d.avis && <FormAvis t={t} intervention={d} onEnvoye={charger} />}
          {d.avis && <p aria-label={`${d.avis.note} / 5`}>{'★'.repeat(d.avis.note)}{'☆'.repeat(5 - d.avis.note)} {d.avis.commentaire}</p>}
        </div>
      ))}
    </>
  );
}

function FormAvis({ t, intervention, onEnvoye }) {
  const [note, setNote] = useState(0);
  const [commentaire, setCommentaire] = useState('');
  const [erreur, setErreur] = useState('');

  async function envoyer() {
    try {
      await api.post(`/interventions/${intervention.id}/avis`, { note, commentaire });
      onEnvoye();
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  return (
    <div style={{ background: 'var(--sable)', padding: 12, borderRadius: 10 }}>
      <strong>{t.avis}</strong>
      <Message erreur={erreur} />
      <div className="etoiles">
        {[1, 2, 3, 4, 5].map((n) => (
          <button type="button" key={n} className={n <= note ? '' : 'eteinte'} onClick={() => setNote(n)} aria-label={`${n} / 5`}>★</button>
        ))}
      </div>
      <input placeholder={t.commentaire} value={commentaire} onChange={(e) => setCommentaire(e.target.value)} style={{ margin: '8px 0' }} />
      <button disabled={note === 0} onClick={envoyer}>{t.noter}</button>
    </div>
  );
}

function MesFactures({ t }) {
  const [factures, setFactures] = useState([]);

  useEffect(() => {
    api.get('/factures').then(({ data }) => setFactures(data.data));
  }, []);

  return (
    <div className="carte">
      {factures.length === 0 && <div className="vide">—</div>}
      <table>
        <tbody>
          {factures.map((f) => (
            <tr key={f.id}>
              <td>{f.numero}</td>
              <td>{date(f.date)}</td>
              <td className="num">{dh(f.montant_ttc)}</td>
              <td><Badge valeur={f.statut_paiement} texte={t[f.statut_paiement]} /></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
