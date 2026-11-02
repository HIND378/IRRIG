import { useEffect, useState } from 'react';
import api, { messageErreur } from '../api';
import { useAuth } from '../auth';
import { Badge, Message, date } from '../components/ui';

/** Transferts de pièces entre agences : demandés par le magasinier, validés par le directeur. */
export default function Transferts() {
  const { user } = useAuth();
  const [transferts, setTransferts] = useState([]);
  const [erreur, setErreur] = useState('');
  const [succes, setSucces] = useState('');

  const charger = () => api.get('/transferts').then(({ data }) => setTransferts(data)).catch((e) => setErreur(messageErreur(e)));

  useEffect(() => {
    charger();
  }, []);

  async function decider(t, decision) {
    setErreur('');
    try {
      await api.put(`/transferts/${t.id}/valider`, { decision });
      setSucces(decision === 'valide' ? `Transfert validé : ${t.quantite} pièce(s) déplacée(s) de ${t.source.ville} vers ${t.destination.ville}.` : 'Transfert refusé.');
      charger();
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  return (
    <>
      <h1>Transferts de stock inter-agences</h1>
      <p className="sous-titre">
        {user.role === 'directeur' ? 'Validez ou refusez les demandes des agences.' : 'Demandes faites depuis la page Stock ; validation par le directeur.'}
      </p>
      <Message erreur={erreur} succes={succes} />
      <div className="carte table-scroll">
        <table>
          <thead>
            <tr><th>Date</th><th>Pièce</th><th className="num">Qté</th><th>De</th><th>Vers</th><th>Demandé par</th><th>Statut</th>{user.role === 'directeur' && <th></th>}</tr>
          </thead>
          <tbody>
            {transferts.map((t) => (
              <tr key={t.id}>
                <td>{date(t.created_at)}</td>
                <td>{t.piece.libelle}</td>
                <td className="num">{t.quantite}</td>
                <td>{t.source.ville}</td>
                <td>{t.destination.ville}</td>
                <td>{t.demandeur?.name}</td>
                <td><Badge valeur={t.statut} />{t.directeur && <div><small>par {t.directeur.name}</small></div>}</td>
                {user.role === 'directeur' && (
                  <td>
                    {t.statut === 'demande' && (
                      <div className="actions">
                        <button className="petit" onClick={() => decider(t, 'valide')}>Valider</button>
                        <button className="danger petit" onClick={() => decider(t, 'refuse')}>Refuser</button>
                      </div>
                    )}
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
        {transferts.length === 0 && <div className="vide">Aucun transfert.</div>}
      </div>
    </>
  );
}
