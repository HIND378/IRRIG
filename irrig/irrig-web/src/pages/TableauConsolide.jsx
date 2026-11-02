import { useEffect, useState } from 'react';
import { BarElement, CategoryScale, Chart as ChartJS, Legend, LinearScale, Tooltip } from 'chart.js';
import { Bar } from 'react-chartjs-2';
import api, { messageErreur } from '../api';
import { Kpi, Message, dh } from '../components/ui';
import { moisCourant } from './TableauDeBord';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

/** Tableau de bord du directeur multi-agences : les 3 agences côte à côte. */
export default function TableauConsolide() {
  const [data, setData] = useState(null);
  const [mois, setMois] = useState(moisCourant);
  const [erreur, setErreur] = useState('');

  useEffect(() => {
    api
      .get('/kpi/consolides', { params: { mois } })
      .then(({ data }) => setData(data))
      .catch((e) => setErreur(messageErreur(e)));
  }, [mois]);

  if (!data) return <Message erreur={erreur} />;
  const villes = data.agences.map((a) => a.agence);
  const graphe = (titre, cle, couleur) => (
    <div className="carte">
      <h2>{titre}</h2>
      <Bar
        data={{ labels: villes, datasets: [{ label: titre, data: data.agences.map((a) => a[cle] ?? 0), backgroundColor: couleur, borderRadius: 6 }] }}
        options={{ plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }}
      />
    </div>
  );

  return (
    <>
      <div className="entete">
        <div>
          <h1>Tableau de bord consolidé</h1>
          <p className="sous-titre">Comparaison des agences d'Agadir, Rabat et Casablanca.</p>
        </div>
        <input type="month" value={mois} onChange={(e) => setMois(e.target.value)} style={{ width: 'auto' }} />
      </div>

      <div className="grille grille-4" style={{ marginBottom: 18 }}>
        <Kpi libelle="Interventions clôturées (réseau)" valeur={data.total.interventions_cloturees} />
        <Kpi libelle="Devis acceptés (TTC)" valeur={dh(data.total.chiffre_affaires_ttc)} />
        <Kpi libelle="Satisfaction moyenne" valeur={data.total.satisfaction} unite="/ 5" />
        <Kpi libelle="Alertes de stock" valeur={data.total.alertes_stock} />
      </div>

      <div className="carte table-scroll">
        <table>
          <thead>
            <tr>
              <th>Agence</th>
              <th className="num">Clôturées</th>
              <th className="num">Délai moyen</th>
              <th className="num">Coût moyen pièces</th>
              <th className="num">Satisfaction</th>
              <th className="num">En attente</th>
              <th className="num">Alertes stock</th>
            </tr>
          </thead>
          <tbody>
            {data.agences.map((a) => (
              <tr key={a.agence_id}>
                <td><strong>{a.agence}</strong></td>
                <td className="num">{a.interventions_cloturees}</td>
                <td className="num">{a.delai_moyen_jours ?? '—'} j</td>
                <td className="num">{dh(a.cout_moyen_pieces)}</td>
                <td className="num">{a.satisfaction ?? '—'} / 5</td>
                <td className="num">{a.interventions_en_attente.nouvelle + a.interventions_en_attente.planifiee + a.interventions_en_attente.en_cours}</td>
                <td className="num">{a.alertes_stock}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="grille grille-2">
        {graphe('Interventions clôturées', 'interventions_cloturees', '#2f8a5c')}
        {graphe('Délai moyen (jours)', 'delai_moyen_jours', '#d98a1c')}
        {graphe('Coût moyen en pièces (DH)', 'cout_moyen_pieces', '#1e6fa8')}
        {graphe('Satisfaction (/5)', 'satisfaction', '#7a5af5')}
      </div>
    </>
  );
}
