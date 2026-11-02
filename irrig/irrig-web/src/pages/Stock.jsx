import { useEffect, useState } from 'react';
import api, { messageErreur } from '../api';
import { useAuth } from '../auth';
import { Badge, Message, Modal, dh } from '../components/ui';

export default function Stock() {
  const { user } = useAuth();
  const [stocks, setStocks] = useState([]);
  const [alerte, setAlerte] = useState(false);
  const [agences, setAgences] = useState([]);
  const [agenceId, setAgenceId] = useState('');
  const [modal, setModal] = useState(null);
  const [erreur, setErreur] = useState('');
  const [succes, setSucces] = useState('');

  async function charger() {
    try {
      const { data } = await api.get('/stocks', { params: { alerte: alerte ? 1 : undefined, agence_id: agenceId || undefined } });
      setStocks(data);
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  useEffect(() => {
    charger();
  }, [alerte, agenceId]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    api.get('/agences').then(({ data }) => setAgences(data)).catch(() => {});
  }, []);

  async function envoyer(e) {
    e.preventDefault();
    try {
      if (modal.type === 'reception') {
        await api.post(`/stocks/${modal.stock.id}/reception`, { quantite: modal.quantite });
        setSucces(`${modal.quantite} × ${modal.stock.piece.libelle} réceptionné(s).`);
      } else if (modal.type === 'inventaire') {
        await api.put(`/stocks/${modal.stock.id}`, { quantite: modal.quantite, seuil_alerte: modal.seuil_alerte });
        setSucces('Stock ajusté après inventaire.');
      } else if (modal.type === 'transfert') {
        await api.post('/transferts', { agence_source_id: modal.agence_source_id, piece_id: modal.stock.piece_id, quantite: modal.quantite });
        setSucces('Demande de transfert envoyée au directeur pour validation.');
      } else if (modal.type === 'piece') {
        await api.post('/pieces', { reference: modal.reference, libelle: modal.libelle, prix: modal.prix });
        setSucces('Pièce ajoutée au catalogue de toutes les agences.');
      }
      setModal(null);
      charger();
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  const ouvrir = (m) => { setErreur(''); setModal(m); };
  const autres = (stock) => agences.filter((a) => a.id !== stock.agence_id);

  return (
    <>
      <div className="entete">
        <div>
          <h1>Stock de pièces détachées</h1>
          <p className="sous-titre">Mis à jour automatiquement à chaque clôture d'intervention.</p>
        </div>
        {user.role === 'magasinier' && <button onClick={() => ouvrir({ type: 'piece', reference: '', libelle: '', prix: '' })}>+ Pièce au catalogue</button>}
      </div>
      <Message erreur={!modal && erreur} succes={succes} />

      <div className="carte">
        <div className="filtres">
          {user.role === 'directeur' && (
            <select value={agenceId} onChange={(e) => setAgenceId(e.target.value)}>
              <option value="">Les 3 agences</option>
              {agences.map((a) => <option key={a.id} value={a.id}>{a.ville}</option>)}
            </select>
          )}
          <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontWeight: 500 }}>
            <input type="checkbox" checked={alerte} onChange={(e) => setAlerte(e.target.checked)} style={{ width: 'auto' }} />
            Seulement les pièces sous le seuil
          </label>
        </div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr><th>Référence</th><th>Pièce</th>{user.role === 'directeur' && <th>Agence</th>}<th className="num">Prix</th><th className="num">Quantité</th><th className="num">Seuil</th><th>État</th><th></th></tr>
            </thead>
            <tbody>
              {stocks.map((s) => (
                <tr key={s.id} className={s.sous_seuil ? 'alerte' : ''}>
                  <td>{s.piece.reference}</td>
                  <td>{s.piece.libelle}</td>
                  {user.role === 'directeur' && <td>{s.agence.ville}</td>}
                  <td className="num">{dh(s.piece.prix)}</td>
                  <td className="num"><strong>{s.quantite}</strong></td>
                  <td className="num">{s.seuil_alerte}</td>
                  <td>{s.sous_seuil ? <Badge valeur="alerte" texte="À commander" /> : <Badge valeur="ok" texte="OK" />}</td>
                  <td>
                    <div className="actions">
                      <button className="petit" onClick={() => ouvrir({ type: 'reception', stock: s, quantite: 1 })}>Réception</button>
                      <button className="secondaire petit" onClick={() => ouvrir({ type: 'inventaire', stock: s, quantite: s.quantite, seuil_alerte: s.seuil_alerte })}>Inventaire</button>
                      {user.role !== 'directeur' && (
                        <button className="secondaire petit" onClick={() => ouvrir({ type: 'transfert', stock: s, quantite: 1, agence_source_id: autres(s)[0]?.id })}>
                          Demander un transfert
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {stocks.length === 0 && <div className="vide">Aucune pièce à afficher.</div>}
        </div>
      </div>

      {modal && (
        <Modal
          titre={{ reception: 'Réception de pièces', inventaire: 'Ajustement après inventaire', transfert: 'Demande de transfert inter-agences', piece: 'Nouvelle pièce' }[modal.type]}
          onFermer={() => setModal(null)}
        >
          <form onSubmit={envoyer}>
            <Message erreur={erreur} />
            {modal.stock && <p className="sous-titre">{modal.stock.piece.reference} — {modal.stock.piece.libelle} (stock actuel : {modal.stock.quantite})</p>}
            {modal.type === 'piece' && (
              <>
                <div className="ligne-champs">
                  <div className="champ"><label>Référence</label><input value={modal.reference} onChange={(e) => setModal({ ...modal, reference: e.target.value })} required /></div>
                  <div className="champ"><label>Prix (DH)</label><input type="number" min="0" step="0.01" value={modal.prix} onChange={(e) => setModal({ ...modal, prix: e.target.value })} required /></div>
                </div>
                <div className="champ"><label>Libellé</label><input value={modal.libelle} onChange={(e) => setModal({ ...modal, libelle: e.target.value })} required /></div>
              </>
            )}
            {modal.type === 'transfert' && (
              <div className="champ">
                <label>Agence qui fournit la pièce</label>
                <select value={modal.agence_source_id} onChange={(e) => setModal({ ...modal, agence_source_id: e.target.value })} required>
                  {autres(modal.stock).map((a) => <option key={a.id} value={a.id}>{a.ville}</option>)}
                </select>
              </div>
            )}
            {modal.type !== 'piece' && (
              <div className="ligne-champs">
                <div className="champ">
                  <label>{modal.type === 'inventaire' ? 'Quantité comptée' : 'Quantité'}</label>
                  <input type="number" min={modal.type === 'inventaire' ? 0 : 1} value={modal.quantite} onChange={(e) => setModal({ ...modal, quantite: e.target.value })} required />
                </div>
                {modal.type === 'inventaire' && (
                  <div className="champ">
                    <label>Seuil d'alerte</label>
                    <input type="number" min="0" value={modal.seuil_alerte} onChange={(e) => setModal({ ...modal, seuil_alerte: e.target.value })} required />
                  </div>
                )}
              </div>
            )}
            <div className="actions">
              <button type="button" className="secondaire" onClick={() => setModal(null)}>Annuler</button>
              <button type="submit">Valider</button>
            </div>
          </form>
        </Modal>
      )}
    </>
  );
}
