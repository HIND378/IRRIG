import { useEffect, useState } from 'react';
import api, { messageErreur } from '../api';
import { useAuth } from '../auth';
import { Message, libelle } from '../components/ui';

const JOURS = 7;
const cleJour = (d) => `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;

/** Vue par technicien et par jour des interventions planifiées (7 prochains jours). */
export default function Planning() {
  const { user } = useAuth();
  const [debut, setDebut] = useState(() => {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
  });
  const [techniciens, setTechniciens] = useState([]);
  const [missions, setMissions] = useState([]);
  const [agences, setAgences] = useState([]);
  const [agenceId, setAgenceId] = useState(user.agence_id || '');
  const [erreur, setErreur] = useState('');

  useEffect(() => {
    if (user.role === 'directeur') {
      api.get('/agences').then(({ data }) => {
        setAgences(data);
        setAgenceId((a) => a || data[0]?.id);
      });
    }
  }, [user.role]);

  useEffect(() => {
    if (!agenceId) return;
    const params = { agence_id: agenceId, par_page: 200 };
    Promise.all([
      api.get('/techniciens', { params: { agence_id: agenceId } }),
      api.get('/interventions', { params: { ...params, statut: 'planifiee' } }),
      api.get('/interventions', { params: { ...params, statut: 'en_cours' } }),
    ])
      .then(([t, p, c]) => {
        setTechniciens(t.data);
        setMissions([...p.data.data, ...c.data.data]);
      })
      .catch((e) => setErreur(messageErreur(e)));
  }, [agenceId]);

  const jours = Array.from({ length: JOURS }, (_, i) => new Date(debut.getTime() + i * 86400000));
  const decaler = (n) => setDebut(new Date(debut.getTime() + n * 86400000));

  return (
    <>
      <div className="entete">
        <div>
          <h1>Planning des techniciens</h1>
          <p className="sous-titre">Interventions planifiées et en cours, regroupées par technicien.</p>
        </div>
        <div className="actions">
          {user.role === 'directeur' && (
            <select value={agenceId} onChange={(e) => setAgenceId(e.target.value)} style={{ width: 'auto' }}>
              {agences.map((a) => <option key={a.id} value={a.id}>{a.ville}</option>)}
            </select>
          )}
          <button className="secondaire" onClick={() => decaler(-7)}>← Semaine précédente</button>
          <button className="secondaire" onClick={() => decaler(7)}>Semaine suivante →</button>
        </div>
      </div>
      <Message erreur={erreur} />

      <div className="table-scroll">
        <div className="planning" style={{ gridTemplateColumns: `180px repeat(${JOURS}, minmax(120px, 1fr))`, minWidth: 1000 }}>
          <div className="tete">Technicien</div>
          {jours.map((j) => (
            <div className="tete" key={j.toISOString()}>
              {j.toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: '2-digit' })}
            </div>
          ))}
          {techniciens.map((t) => (
            <Ligne key={t.id} technicien={t} jours={jours} missions={missions.filter((m) => m.technicien_id === t.id)} />
          ))}
        </div>
      </div>
      {techniciens.length === 0 && <div className="vide">Aucun technicien dans cette agence.</div>}
    </>
  );
}

function Ligne({ technicien, jours, missions }) {
  return (
    <>
      <div>
        <strong>{technicien.name}</strong>
        <br />
        <small>{technicien.specialite} · zone {technicien.zone}</small>
      </div>
      {jours.map((j) => {
        const duJour = missions.filter((m) => m.date_planifiee && cleJour(new Date(m.date_planifiee)) === cleJour(j));
        return (
          <div key={j.toISOString()}>
            {duJour.map((m) => (
              <div className="mission" key={m.id} title={m.description}>
                {new Date(m.date_planifiee).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })} · n°{m.id}
                <br />
                {m.equipement.exploitation.commune} — {libelle(m.type)}
              </div>
            ))}
          </div>
        );
      })}
    </>
  );
}
