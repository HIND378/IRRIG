import { useEffect, useState } from 'react';
import { ArcElement, Chart as ChartJS, Legend, Tooltip } from 'chart.js';
import { Doughnut } from 'react-chartjs-2';
import api, { messageErreur } from '../api';
import { useAuth } from '../auth';
import { Kpi, Message, dh } from '../components/ui';

ChartJS.register(ArcElement, Tooltip, Legend);

export const moisCourant = () => new Date().toISOString().slice(0, 7);

/** Tableau de bord d'une agence : les 4 indicateurs du MVP. */
export default function TableauDeBord() {
  const { user } = useAuth();
  const [kpi, setKpi] = useState(null);
  const [mois, setMois] = useState(moisCourant);
  const [agences, setAgences] = useState([]);
  const [agenceId, setAgenceId] = useState('');
  const [erreur, setErreur] = useState('');

  useEffect(() => {
    if (user.role === 'directeur') api.get('/agences').then(({ data }) => setAgences(data));
  }, [user.role]);

  useEffect(() => {
    api
      .get('/kpi', { params: { mois, agence_id: agenceId || undefined } })
      .then(({ data }) => setKpi(data))
      .catch((e) => setErreur(messageErreur(e)));
  }, [mois, agenceId]);

  if (!kpi) return <Message erreur={erreur} />;
  const attente = kpi.interventions_en_attente;

  return (
    <>
      <div className="entete">
        <div>
          <h1>Tableau de bord — agence de {kpi.agence}</h1>
          <p className="sous-titre">Indicateurs du mois sélectionné.</p>
        </div>
        <div className="actions">
          {user.role === 'directeur' && (
            <select value={agenceId} onChange={(e) => setAgenceId(e.target.value)} style={{ width: 'auto' }}>
              {agences.map((a) => <option key={a.id} value={a.id}>{a.ville}</option>)}
            </select>
          )}
          <input type="month" value={mois} onChange={(e) => setMois(e.target.value)} style={{ width: 'auto' }} />
        </div>
      </div>
      <Message erreur={erreur} />

      <div className="grille grille-4" style={{ marginBottom: 18 }}>
        <Kpi libelle="Interventions clôturées" valeur={kpi.interventions_cloturees} />
        <Kpi libelle="Délai moyen d'intervention" valeur={kpi.delai_moyen_jours} unite="jours" />
        <Kpi libelle="Coût moyen en pièces" valeur={kpi.cout_moyen_pieces == null ? null : dh(kpi.cout_moyen_pieces)} />
        <Kpi libelle={`Satisfaction (${kpi.nombre_avis} avis)`} valeur={kpi.satisfaction} unite="/ 5" />
      </div>

      <div className="grille grille-2">
        <div className="carte">
          <h2>Interventions en attente</h2>
          <div style={{ maxWidth: 300, margin: '0 auto' }}>
            <Doughnut
              data={{
                labels: ['Nouvelles', 'Planifiées', 'En cours'],
                datasets: [{ data: [attente.nouvelle, attente.planifiee, attente.en_cours], backgroundColor: ['#1e6fa8', '#d98a1c', '#2f8a5c'] }],
              }}
              options={{ plugins: { legend: { position: 'bottom' } } }}
            />
          </div>
        </div>
        <div className="carte">
          <h2>Activité commerciale et stock</h2>
          <div className="grille" style={{ gridTemplateColumns: '1fr 1fr' }}>
            <Kpi libelle="Devis acceptés (TTC)" valeur={dh(kpi.chiffre_affaires_ttc)} />
            <Kpi libelle="Pièces sous le seuil d'alerte" valeur={kpi.alertes_stock} />
          </div>
        </div>
      </div>
    </>
  );
}
