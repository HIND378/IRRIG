import { useEffect, useState } from 'react';
import api, { messageErreur } from '../api';
import { Badge, Message, Modal, date, libelle } from '../components/ui';

/**
 * Version web des missions du technicien (utile en agence ou pour la démonstration).
 * Sur le terrain, le technicien utilise l'application mobile Flutter qui fonctionne hors ligne.
 */
export default function MissionsTechnicien() {
  const [missions, setMissions] = useState([]);
  const [pieces, setPieces] = useState([]);
  const [cloture, setCloture] = useState(null);
  const [erreur, setErreur] = useState('');
  const [succes, setSucces] = useState('');

  const charger = () => api.get('/mes-missions').then(({ data }) => setMissions(data)).catch((e) => setErreur(messageErreur(e)));

  useEffect(() => {
    charger();
    api.get('/pieces').then(({ data }) => setPieces(data));
  }, []);

  async function demarrer(m) {
    try {
      await api.put(`/interventions/${m.id}/demarrer`);
      charger();
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  async function cloturer(e) {
    e.preventDefault();
    const donnees = new FormData();
    donnees.append('rapport', cloture.rapport);
    if (cloture.signature_client) donnees.append('signature_client', cloture.signature_client);
    cloture.pieces.filter((p) => p.piece_id).forEach((p, i) => {
      donnees.append(`pieces[${i}][piece_id]`, p.piece_id);
      donnees.append(`pieces[${i}][quantite]`, p.quantite);
    });
    [...(cloture.photos || [])].forEach((f) => donnees.append('photos[]', f));
    try {
      await api.post(`/interventions/${cloture.mission.id}/cloturer`, donnees);
      setSucces(`Intervention n°${cloture.mission.id} clôturée : stock mis à jour, client prévenu.`);
      setCloture(null);
      charger();
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  const majPiece = (i, champ, v) => setCloture({ ...cloture, pieces: cloture.pieces.map((p, j) => (j === i ? { ...p, [champ]: v } : p)) });

  return (
    <>
      <h1>Mes missions</h1>
      <p className="sous-titre">Interventions planifiées qui vous sont affectées.</p>
      <Message erreur={!cloture && erreur} succes={succes} />

      {missions.length === 0 && <div className="carte vide">Aucune mission à venir.</div>}
      <div className="grille grille-2">
        {missions.map((m) => {
          const ex = m.equipement.exploitation;
          return (
            <div className="carte" key={m.id}>
              <div className="entete">
                <h2>n°{m.id} — {ex.client.nom}</h2>
                <Badge valeur={m.statut} />
              </div>
              <p>
                <strong>{date(m.date_planifiee, true)}</strong> · {ex.commune}
                {ex.latitude && (
                  <> · <a href={`https://www.google.com/maps?q=${ex.latitude},${ex.longitude}`} target="_blank" rel="noreferrer">Itinéraire</a></>
                )}
                <br />
                Tél. client : <a href={`tel:${ex.client.telephone}`}>{ex.client.telephone}</a>
              </p>
              <p>{libelle(m.type)} sur {libelle(m.equipement.type)} n° {m.equipement.numero_serie} ({m.sous_garantie ? 'sous garantie' : 'hors garantie'})</p>
              <p><em>« {m.description} »</em></p>
              {m.equipement.interventions?.length > 0 && (
                <p><small>Historique : {m.equipement.interventions.map((h) => `${date(h.date_cloture)} ${libelle(h.type)}`).join(' · ')}</small></p>
              )}
              <div className="actions">
                {m.statut === 'planifiee' && <button className="secondaire" onClick={() => demarrer(m)}>Démarrer</button>}
                <button onClick={() => { setErreur(''); setCloture({ mission: m, rapport: '', signature_client: '', pieces: [{ piece_id: '', quantite: 1 }], photos: [] }); }}>
                  Saisir le rapport
                </button>
              </div>
            </div>
          );
        })}
      </div>

      {cloture && (
        <Modal titre={`Rapport — intervention n°${cloture.mission.id}`} onFermer={() => setCloture(null)}>
          <form onSubmit={cloturer}>
            <Message erreur={erreur} />
            <div className="champ">
              <label>Travaux réalisés</label>
              <textarea rows="4" value={cloture.rapport} onChange={(e) => setCloture({ ...cloture, rapport: e.target.value })} required />
            </div>
            <label>Pièces utilisées</label>
            {cloture.pieces.map((p, i) => (
              <div className="ligne-champs" key={i} style={{ gridTemplateColumns: '3fr 1fr' }}>
                <div className="champ">
                  <select value={p.piece_id} onChange={(e) => majPiece(i, 'piece_id', e.target.value)}>
                    <option value="">Aucune</option>
                    {pieces.map((pc) => <option key={pc.id} value={pc.id}>{pc.reference} — {pc.libelle}</option>)}
                  </select>
                </div>
                <div className="champ">
                  <input type="number" min="1" value={p.quantite} onChange={(e) => majPiece(i, 'quantite', e.target.value)} />
                </div>
              </div>
            ))}
            <button type="button" className="secondaire petit" onClick={() => setCloture({ ...cloture, pieces: [...cloture.pieces, { piece_id: '', quantite: 1 }] })}>
              + Pièce
            </button>
            <div className="champ" style={{ marginTop: 12 }}>
              <label>Photos (5 maximum)</label>
              <input type="file" accept="image/*" multiple onChange={(e) => setCloture({ ...cloture, photos: e.target.files })} />
            </div>
            <div className="champ">
              <label>Nom du client (signature)</label>
              <input value={cloture.signature_client} onChange={(e) => setCloture({ ...cloture, signature_client: e.target.value })} />
            </div>
            <div className="actions">
              <button type="button" className="secondaire" onClick={() => setCloture(null)}>Annuler</button>
              <button type="submit">Clôturer l'intervention</button>
            </div>
          </form>
        </Modal>
      )}
    </>
  );
}
