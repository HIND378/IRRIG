import { useEffect, useState } from 'react';
import api, { messageErreur } from '../api';
import { useAuth } from '../auth';
import { Badge, Message, Modal, date, dh, libelle } from '../components/ui';

export default function Interventions() {
  const { user } = useAuth();
  const gestionnaire = ['responsable', 'directeur'].includes(user.role);
  const [liste, setListe] = useState([]);
  const [statut, setStatut] = useState(gestionnaire ? 'nouvelle' : '');
  const [agenceId, setAgenceId] = useState('');
  const [agences, setAgences] = useState([]);
  const [techniciens, setTechniciens] = useState([]);
  const [planif, setPlanif] = useState(null);
  const [detail, setDetail] = useState(null);
  const [erreur, setErreur] = useState('');
  const [succes, setSucces] = useState('');

  async function charger() {
    try {
      const { data } = await api.get('/interventions', {
        params: { statut: statut || undefined, agence_id: agenceId || undefined, par_page: 100 },
      });
      setListe(data.data);
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  useEffect(() => {
    charger();
  }, [statut, agenceId]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (user.role === 'directeur') api.get('/agences').then(({ data }) => setAgences(data));
  }, [user.role]);

  async function ouvrirPlanif(i) {
    setErreur('');
    const { data } = await api.get('/techniciens', { params: { agence_id: i.agence_id } });
    setTechniciens(data);
    const demain = new Date(Date.now() + 86400000);
    demain.setHours(9, 0, 0, 0);
    const local = new Date(demain.getTime() - demain.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    setPlanif({ intervention: i, technicien_id: data[0]?.id ?? '', date_planifiee: local });
  }

  async function planifier(e) {
    e.preventDefault();
    try {
      await api.put(`/interventions/${planif.intervention.id}/planifier`, {
        technicien_id: planif.technicien_id,
        date_planifiee: planif.date_planifiee.replace('T', ' ') + ':00',
      });
      setSucces(`Intervention n°${planif.intervention.id} planifiée, le client est prévenu par SMS.`);
      setPlanif(null);
      charger();
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  async function annuler(i) {
    if (!window.confirm(`Annuler l'intervention n°${i.id} ?`)) return;
    try {
      await api.put(`/interventions/${i.id}/annuler`);
      charger();
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  return (
    <>
      <h1>Demandes d'intervention</h1>
      <p className="sous-titre">
        {gestionnaire ? 'Affectez chaque demande à un technicien de l’agence.' : 'Suivi des interventions de vos clients.'}
      </p>
      <Message erreur={!planif && erreur} succes={succes} />

      <div className="carte">
        <div className="filtres">
          <select value={statut} onChange={(e) => setStatut(e.target.value)}>
            <option value="">Tous les statuts</option>
            <option value="nouvelle">Nouvelles (à planifier)</option>
            <option value="planifiee">Planifiées</option>
            <option value="en_cours">En cours</option>
            <option value="cloturee">Clôturées</option>
            <option value="annulee">Annulées</option>
          </select>
          {user.role === 'directeur' && (
            <select value={agenceId} onChange={(e) => setAgenceId(e.target.value)}>
              <option value="">Les 3 agences</option>
              {agences.map((a) => <option key={a.id} value={a.id}>{a.ville}</option>)}
            </select>
          )}
        </div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr><th>N°</th><th>Demandée le</th><th>Client / commune</th><th>Équipement</th><th>Problème</th><th>Technicien</th><th>Statut</th><th></th></tr>
            </thead>
            <tbody>
              {liste.map((i) => (
                <tr key={i.id}>
                  <td>{i.id}</td>
                  <td>{date(i.created_at)}<br /><small>{i.canal}</small></td>
                  <td>{i.equipement.exploitation.client.nom}<br /><small>{i.equipement.exploitation.commune} · {i.agence.ville}</small></td>
                  <td>
                    {libelle(i.equipement.type)}
                    <br />
                    <small>{i.sous_garantie ? 'Sous garantie' : 'Hors garantie'}</small>
                  </td>
                  <td>{libelle(i.type)} — {i.description.slice(0, 70)}{i.description.length > 70 ? '…' : ''}</td>
                  <td>{i.technicien?.name || '—'}{i.date_planifiee && <><br /><small>{date(i.date_planifiee, true)}</small></>}</td>
                  <td><Badge valeur={i.statut} /></td>
                  <td>
                    <div className="actions">
                      <button className="secondaire petit" onClick={() => setDetail(i)}>Voir</button>
                      {gestionnaire && ['nouvelle', 'planifiee'].includes(i.statut) && (
                        <button className="petit" onClick={() => ouvrirPlanif(i)}>{i.statut === 'nouvelle' ? 'Planifier' : 'Replanifier'}</button>
                      )}
                      {gestionnaire && ['nouvelle', 'planifiee'].includes(i.statut) && (
                        <button className="danger petit" onClick={() => annuler(i)}>Annuler</button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {liste.length === 0 && <div className="vide">Aucune intervention pour ce filtre.</div>}
        </div>
      </div>

      {planif && (
        <Modal titre={`Planifier l'intervention n°${planif.intervention.id}`} onFermer={() => setPlanif(null)}>
          <form onSubmit={planifier}>
            <Message erreur={erreur} />
            <p className="sous-titre">
              {planif.intervention.equipement.exploitation.client.nom} — {planif.intervention.equipement.exploitation.commune}
            </p>
            <div className="champ">
              <label>Technicien</label>
              <select value={planif.technicien_id} onChange={(e) => setPlanif({ ...planif, technicien_id: e.target.value })} required>
                {techniciens.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name} — {t.specialite || 'polyvalent'}, zone {t.zone || '?'} ({t.missions_ouvertes} mission(s) en cours)
                  </option>
                ))}
              </select>
            </div>
            <div className="champ">
              <label>Date et heure de passage</label>
              <input type="datetime-local" value={planif.date_planifiee} onChange={(e) => setPlanif({ ...planif, date_planifiee: e.target.value })} required />
            </div>
            <div className="actions">
              <button type="button" className="secondaire" onClick={() => setPlanif(null)}>Annuler</button>
              <button type="submit">Affecter</button>
            </div>
          </form>
        </Modal>
      )}

      {detail && (
        <Modal titre={`Intervention n°${detail.id}`} onFermer={() => setDetail(null)}>
          <p><Badge valeur={detail.statut} /> {libelle(detail.type)} · {libelle(detail.equipement.type)} n° {detail.equipement.numero_serie}</p>
          <p><strong>Problème signalé :</strong> {detail.description}</p>
          {detail.rapport && <p><strong>Rapport du technicien :</strong> {detail.rapport}</p>}
          {detail.lignes_consommation?.length > 0 && (
            <>
              <h3>Pièces utilisées</h3>
              <ul>
                {detail.lignes_consommation.map((l) => (
                  <li key={l.id}>{l.quantite} × {l.piece.libelle} ({dh(l.prix_unitaire)})</li>
                ))}
              </ul>
            </>
          )}
          {detail.photos?.length > 0 && (
            <div className="actions">
              {detail.photos.map((p) => <a key={p.id} href={p.url} target="_blank" rel="noreferrer"><img src={p.url} alt="Photo d'intervention" width="110" /></a>)}
            </div>
          )}
          {detail.signature_client && <p><strong>Signé par :</strong> {detail.signature_client}</p>}
          {detail.facture && <p><strong>Facture :</strong> {detail.facture.numero} — {dh(detail.facture.montant_ttc)} <Badge valeur={detail.facture.statut_paiement} /></p>}
          {detail.avis && <p><strong>Avis du client :</strong> {'★'.repeat(detail.avis.note)}{'☆'.repeat(5 - detail.avis.note)} {detail.avis.commentaire}</p>}
          <div className="actions"><button onClick={() => setDetail(null)}>Fermer</button></div>
        </Modal>
      )}
    </>
  );
}
