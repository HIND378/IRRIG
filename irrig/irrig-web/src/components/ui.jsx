/** Composants réutilisables de l'interface. */

export const LIBELLES = {
  // statuts d'intervention
  nouvelle: 'Nouvelle',
  planifiee: 'Planifiée',
  en_cours: 'En cours',
  cloturee: 'Clôturée',
  annulee: 'Annulée',
  // devis / transferts / factures
  envoye: 'Envoyé',
  accepte: 'Accepté',
  refuse: 'Refusé',
  demande: 'Demandé',
  valide: 'Validé',
  payee: 'Payée',
  impayee: 'Impayée',
  // types d'équipement
  goutte_a_goutte: 'Goutte-à-goutte',
  pompe_solaire: 'Pompe solaire',
  capteur_humidite: "Capteur d'humidité",
  programmateur: 'Programmateur',
  filtration: 'Filtration',
  // types de client
  petit_exploitant: 'Petit exploitant',
  cooperative: 'Coopérative',
  entreprise: 'Entreprise agricole',
  // types d'intervention
  installation: 'Installation',
  maintenance: 'Maintenance',
  panne: 'Panne',
};

export const libelle = (cle) => LIBELLES[cle] || cle;

export function Badge({ valeur, texte }) {
  return <span className={`badge b-${valeur}`}>{texte || libelle(valeur)}</span>;
}

export function Message({ erreur, succes }) {
  if (erreur) return <div className="message erreur">{erreur}</div>;
  if (succes) return <div className="message succes">{succes}</div>;
  return null;
}

export function Modal({ titre, children, onFermer }) {
  return (
    <div className="fond-modal" onMouseDown={(e) => e.target === e.currentTarget && onFermer()}>
      <div className="modal" role="dialog" aria-label={titre}>
        <h2>{titre}</h2>
        {children}
      </div>
    </div>
  );
}

export function Kpi({ libelle: texte, valeur, unite }) {
  return (
    <div className="kpi">
      <div className="libelle">{texte}</div>
      <div className="valeur">
        {valeur ?? '—'}
        {valeur != null && unite && <span className="unite">{unite}</span>}
      </div>
    </div>
  );
}

export const dh = (n) =>
  n == null ? '—' : `${Number(n).toLocaleString('fr-MA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} DH`;

export const date = (d, heure = false) => {
  if (!d) return '—';
  const opts = heure
    ? { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }
    : { day: '2-digit', month: '2-digit', year: 'numeric' };
  return new Date(d).toLocaleString('fr-FR', opts);
};
