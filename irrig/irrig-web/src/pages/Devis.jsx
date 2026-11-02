import { useEffect, useState } from 'react';
import api, { messageErreur } from '../api';
import { Badge, Message, Modal, date, dh } from '../components/ui';

const LIGNE_VIDE = { designation: '', quantite: 1, prix_unitaire: 0 };

/** Cycle commercial (séquence 8.2) : devis -> accepté (commande + facture) ou refusé avec motif. */
export default function Devis() {
  const [devis, setDevis] = useState([]);
  const [statut, setStatut] = useState('');
  const [clients, setClients] = useState([]);
  const [nouveau, setNouveau] = useState(null);
  const [refus, setRefus] = useState(null);
  const [erreur, setErreur] = useState('');
  const [succes, setSucces] = useState('');

  async function charger() {
    const { data } = await api.get('/devis', { params: { statut: statut || undefined, par_page: 100 } });
    setDevis(data.data);
  }

  useEffect(() => {
    charger().catch((e) => setErreur(messageErreur(e)));
  }, [statut]); // eslint-disable-line react-hooks/exhaustive-deps

  async function ouvrirNouveau() {
    setErreur('');
    if (clients.length === 0) setClients((await api.get('/clients', { params: { par_page: 200 } })).data.data);
    setNouveau({ client_id: '', lignes: [{ ...LIGNE_VIDE }] });
  }

  const totalHt = (lignes) => lignes.reduce((s, l) => s + Number(l.quantite) * Number(l.prix_unitaire), 0);

  function majLigne(i, champ, valeur) {
    const lignes = nouveau.lignes.map((l, j) => (j === i ? { ...l, [champ]: valeur } : l));
    setNouveau({ ...nouveau, lignes });
  }

  async function creer(e) {
    e.preventDefault();
    try {
      await api.post('/devis', nouveau);
      setNouveau(null);
      setSucces('Devis créé et notifié au client.');
      charger();
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  async function accepter(d) {
    setErreur('');
    try {
      await api.put(`/devis/${d.id}/accepter`);
      setSucces(`Devis ${d.numero} accepté : commande et facture créées.`);
      charger();
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  async function refuser(e) {
    e.preventDefault();
    try {
      await api.put(`/devis/${refus.id}/refuser`, { motif_refus: refus.motif });
      setRefus(null);
      setSucces('Devis marqué comme perdu.');
      charger();
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  /** La page imprimable est protégée par le jeton : on la récupère puis on l'ouvre dans un nouvel onglet. */
  async function imprimer(d) {
    const fenetre = window.open('', '_blank');
    const { data } = await api.get(`/devis/${d.id}/imprimer`, { responseType: 'text', headers: { Accept: 'text/html' } });
    fenetre.document.open();
    fenetre.document.write(data);
    fenetre.document.close();
  }

  return (
    <>
      <div className="entete">
        <div>
          <h1>Devis et commandes</h1>
          <p className="sous-titre">Un devis accepté devient une commande et génère sa facture.</p>
        </div>
        <button onClick={ouvrirNouveau}>+ Nouveau devis</button>
      </div>
      <Message erreur={!nouveau && !refus && erreur} succes={succes} />

      <div className="carte">
        <div className="filtres">
          <select value={statut} onChange={(e) => setStatut(e.target.value)}>
            <option value="">Tous les statuts</option>
            <option value="envoye">Envoyés</option>
            <option value="accepte">Acceptés</option>
            <option value="refuse">Refusés</option>
          </select>
        </div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr><th>N°</th><th>Date</th><th>Client</th><th>Commercial</th><th className="num">Montant TTC</th><th>Statut</th><th></th></tr>
            </thead>
            <tbody>
              {devis.map((d) => (
                <tr key={d.id}>
                  <td>{d.numero}</td>
                  <td>{date(d.date)}</td>
                  <td>{d.client?.nom}</td>
                  <td>{d.commercial?.name}</td>
                  <td className="num">{dh(d.montant_ttc)}</td>
                  <td>
                    <Badge valeur={d.statut} />
                    {d.motif_refus && <div><small>{d.motif_refus}</small></div>}
                  </td>
                  <td>
                    <div className="actions">
                      <button className="secondaire petit" onClick={() => imprimer(d)}>Imprimer</button>
                      {d.statut === 'envoye' && (
                        <>
                          <button className="petit" onClick={() => accepter(d)}>Accepter</button>
                          <button className="danger petit" onClick={() => { setErreur(''); setRefus({ id: d.id, motif: '' }); }}>Refuser</button>
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {devis.length === 0 && <div className="vide">Aucun devis.</div>}
        </div>
      </div>

      {nouveau && (
        <Modal titre="Nouveau devis" onFermer={() => setNouveau(null)}>
          <form onSubmit={creer}>
            <Message erreur={erreur} />
            <div className="champ">
              <label>Client</label>
              <select value={nouveau.client_id} onChange={(e) => setNouveau({ ...nouveau, client_id: e.target.value })} required>
                <option value="">Choisir un client…</option>
                {clients.map((c) => <option key={c.id} value={c.id}>{c.nom} — {c.telephone}</option>)}
              </select>
            </div>
            {nouveau.lignes.map((l, i) => (
              <div className="ligne-champs" key={i} style={{ gridTemplateColumns: '2fr 0.7fr 1fr auto', alignItems: 'end' }}>
                <div className="champ">
                  <label>Désignation</label>
                  <input value={l.designation} onChange={(e) => majLigne(i, 'designation', e.target.value)} required />
                </div>
                <div className="champ">
                  <label>Qté</label>
                  <input type="number" min="0.01" step="0.01" value={l.quantite} onChange={(e) => majLigne(i, 'quantite', e.target.value)} required />
                </div>
                <div className="champ">
                  <label>Prix unit. HT</label>
                  <input type="number" min="0" step="0.01" value={l.prix_unitaire} onChange={(e) => majLigne(i, 'prix_unitaire', e.target.value)} required />
                </div>
                <div className="champ">
                  <button type="button" className="secondaire petit" disabled={nouveau.lignes.length === 1}
                    onClick={() => setNouveau({ ...nouveau, lignes: nouveau.lignes.filter((_, j) => j !== i) })}>✕</button>
                </div>
              </div>
            ))}
            <button type="button" className="secondaire petit" onClick={() => setNouveau({ ...nouveau, lignes: [...nouveau.lignes, { ...LIGNE_VIDE }] })}>
              + Ligne
            </button>
            <p>
              Total HT : <strong>{dh(totalHt(nouveau.lignes))}</strong> · TTC (TVA 20 %) : <strong>{dh(totalHt(nouveau.lignes) * 1.2)}</strong>
            </p>
            <div className="actions">
              <button type="button" className="secondaire" onClick={() => setNouveau(null)}>Annuler</button>
              <button type="submit">Créer et envoyer</button>
            </div>
          </form>
        </Modal>
      )}

      {refus && (
        <Modal titre="Devis refusé" onFermer={() => setRefus(null)}>
          <form onSubmit={refuser}>
            <Message erreur={erreur} />
            <div className="champ">
              <label>Motif du refus (alimente les statistiques commerciales)</label>
              <input value={refus.motif} onChange={(e) => setRefus({ ...refus, motif: e.target.value })} required placeholder="Prix, délai, concurrent…" />
            </div>
            <div className="actions">
              <button type="button" className="secondaire" onClick={() => setRefus(null)}>Annuler</button>
              <button type="submit" className="danger">Marquer perdu</button>
            </div>
          </form>
        </Modal>
      )}
    </>
  );
}
