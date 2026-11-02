import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import api, { messageErreur } from '../api';
import { useAuth } from '../auth';
import { Badge, Message, Modal, date, dh, libelle } from '../components/ui';

const TYPES_EQ = ['goutte_a_goutte', 'pompe_solaire', 'capteur_humidite', 'programmateur', 'filtration'];

export default function ClientFiche() {
  const { id } = useParams();
  const { user } = useAuth();
  const [client, setClient] = useState(null);
  const [erreur, setErreur] = useState('');
  const [succes, setSucces] = useState('');
  const [modal, setModal] = useState(null); // {type, ...}
  const [form, setForm] = useState({});
  const [documents, setDocuments] = useState({});

  const peutArchiver = ['responsable', 'directeur'].includes(user.role);

  const charger = useCallback(async () => {
    try {
      const { data } = await api.get(`/clients/${id}`);
      setClient(data);
      if (peutArchiver) {
        const docs = {};
        await Promise.all(
          data.exploitations.map(async (ex) => {
            docs[ex.id] = (await api.get(`/exploitations/${ex.id}/documents`)).data;
          }),
        );
        setDocuments(docs);
      }
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }, [id, peutArchiver]);

  useEffect(() => {
    charger();
  }, [charger]);

  function ouvrir(type, initial = {}) {
    setErreur('');
    setForm(initial);
    setModal({ type, ...initial });
  }

  async function envoyer(e) {
    e.preventDefault();
    try {
      if (modal.type === 'exploitation') {
        await api.post(`/clients/${id}/exploitations`, form);
        setSucces('Exploitation ajoutée.');
      } else if (modal.type === 'equipement') {
        await api.post(`/exploitations/${modal.exploitation_id}/equipements`, form);
        setSucces('Équipement ajouté au parc installé.');
      } else if (modal.type === 'intervention') {
        await api.post('/interventions', { ...form, equipement_id: modal.equipement_id });
        setSucces("Demande d'intervention enregistrée. Elle apparaît dans la liste des demandes de l'agence.");
      } else if (modal.type === 'document') {
        const donnees = new FormData();
        Object.entries(form).forEach(([k, v]) => donnees.append(k, v));
        await api.post(`/exploitations/${modal.exploitation_id}/documents`, donnees);
        setSucces('Document archivé.');
      }
      setModal(null);
      charger();
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  if (!client) return <Message erreur={erreur} />;

  const champ = (nom, texte, props = {}) => (
    <div className="champ">
      <label>{texte}</label>
      <input value={form[nom] ?? ''} onChange={(e) => setForm({ ...form, [nom]: e.target.value })} {...props} />
    </div>
  );

  return (
    <>
      <p><Link to="/clients">← Retour aux clients</Link></p>
      <div className="entete">
        <div>
          <h1>{client.nom}</h1>
          <p className="sous-titre">
            {libelle(client.type)} · {client.telephone} · Agence de {client.agence.ville} · Langue : {client.langue === 'ar' ? 'arabe' : 'français'}
          </p>
        </div>
        <button onClick={() => ouvrir('exploitation')}>+ Exploitation</button>
      </div>
      <Message erreur={!modal && erreur} succes={succes} />

      {client.exploitations.map((ex) => (
        <div className="carte" key={ex.id}>
          <div className="entete">
            <div>
              <h2>{ex.nom || 'Exploitation'} — {ex.commune}</h2>
              <p className="sous-titre">
                {ex.culture || 'Culture non renseignée'} · {ex.superficie_ha ?? '?'} ha
                {ex.latitude && ` · GPS ${ex.latitude}, ${ex.longitude}`}
              </p>
            </div>
            <div className="actions">
              <button className="secondaire petit" onClick={() => ouvrir('equipement', { exploitation_id: ex.id, type: 'goutte_a_goutte' })}>
                + Équipement
              </button>
              {peutArchiver && (
                <button className="secondaire petit" onClick={() => ouvrir('document', { exploitation_id: ex.id, type: 'plan' })}>
                  + Archiver un document
                </button>
              )}
            </div>
          </div>

          <div className="table-scroll">
            <table>
              <thead>
                <tr><th>Équipement</th><th>N° de série</th><th>Installé le</th><th>Garantie</th><th>Dernières interventions</th><th></th></tr>
              </thead>
              <tbody>
                {ex.equipements.map((eq) => (
                  <tr key={eq.id}>
                    <td>{libelle(eq.type)} {eq.modele && <small>({eq.modele})</small>}</td>
                    <td>{eq.numero_serie}</td>
                    <td>{date(eq.date_installation)}</td>
                    <td>{eq.sous_garantie ? <Badge valeur="ok" texte={`jusqu'au ${date(eq.fin_garantie)}`} /> : <Badge valeur="alerte" texte="Expirée" />}</td>
                    <td>
                      {eq.interventions.length === 0 && '—'}
                      {eq.interventions.map((i) => (
                        <div key={i.id}>{date(i.created_at)} · {libelle(i.type)} <Badge valeur={i.statut} /></div>
                      ))}
                    </td>
                    <td>
                      <button className="petit" onClick={() => ouvrir('intervention', { equipement_id: eq.id, type: 'panne', canal: 'telephone' })}>
                        Signaler
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            {ex.equipements.length === 0 && <div className="vide">Aucun équipement installé.</div>}
          </div>

          {peutArchiver && (documents[ex.id] || []).length > 0 && (
            <>
              <h3 style={{ marginTop: 16 }}>Archives du projet</h3>
              <ul>
                {documents[ex.id].map((d) => (
                  <li key={d.id}>
                    <a href={d.url} target="_blank" rel="noreferrer">{d.titre}</a> — {d.type.replace('_', ' ')} · {date(d.created_at)} · {d.auteur?.name}
                  </li>
                ))}
              </ul>
            </>
          )}
        </div>
      ))}

      <div className="grille grille-2">
        <div className="carte">
          <h2>Devis</h2>
          {client.devis.length === 0 ? <div className="vide">Aucun devis.</div> : (
            <table>
              <tbody>
                {client.devis.map((d) => (
                  <tr key={d.id}><td>{d.numero}</td><td>{date(d.date)}</td><td className="num">{dh(d.montant_ttc)}</td><td><Badge valeur={d.statut} /></td></tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
        <div className="carte">
          <h2>Factures</h2>
          {client.factures.length === 0 ? <div className="vide">Aucune facture.</div> : (
            <table>
              <tbody>
                {client.factures.map((f) => (
                  <tr key={f.id}><td>{f.numero}</td><td>{date(f.date)}</td><td className="num">{dh(f.montant_ttc)}</td><td><Badge valeur={f.statut_paiement} /></td></tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>

      {modal && (
        <Modal
          titre={{ exploitation: 'Nouvelle exploitation', equipement: 'Nouvel équipement installé', intervention: "Demande d'intervention", document: 'Archiver un document' }[modal.type]}
          onFermer={() => setModal(null)}
        >
          <form onSubmit={envoyer}>
            <Message erreur={erreur} />
            {modal.type === 'exploitation' && (
              <>
                {champ('nom', "Nom de l'exploitation (facultatif)")}
                <div className="ligne-champs">{champ('commune', 'Commune / douar', { required: true })}{champ('culture', 'Culture')}</div>
                <div className="ligne-champs">
                  {champ('superficie_ha', 'Superficie (ha)', { type: 'number', step: '0.01', min: 0 })}
                  {champ('latitude', 'Latitude', { type: 'number', step: 'any' })}
                  {champ('longitude', 'Longitude', { type: 'number', step: 'any' })}
                </div>
              </>
            )}
            {modal.type === 'equipement' && (
              <>
                <div className="champ">
                  <label>Type</label>
                  <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
                    {TYPES_EQ.map((t) => <option key={t} value={t}>{libelle(t)}</option>)}
                  </select>
                </div>
                <div className="ligne-champs">{champ('modele', 'Modèle')}{champ('numero_serie', 'N° de série', { required: true })}</div>
                <div className="ligne-champs">
                  {champ('date_installation', "Date d'installation", { type: 'date', required: true })}
                  {champ('fin_garantie', 'Fin de garantie', { type: 'date' })}
                </div>
              </>
            )}
            {modal.type === 'intervention' && (
              <>
                <div className="ligne-champs">
                  <div className="champ">
                    <label>Type</label>
                    <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
                      <option value="panne">Panne</option>
                      <option value="maintenance">Maintenance</option>
                      <option value="installation">Installation</option>
                    </select>
                  </div>
                  <div className="champ">
                    <label>Reçue par</label>
                    <select value={form.canal} onChange={(e) => setForm({ ...form, canal: e.target.value })}>
                      <option value="telephone">Téléphone</option>
                      <option value="whatsapp">WhatsApp</option>
                      <option value="agence">Visite en agence</option>
                    </select>
                  </div>
                </div>
                <div className="champ">
                  <label>Description du problème</label>
                  <textarea rows="4" value={form.description ?? ''} onChange={(e) => setForm({ ...form, description: e.target.value })} required />
                </div>
              </>
            )}
            {modal.type === 'document' && (
              <>
                <div className="ligne-champs">
                  <div className="champ">
                    <label>Type</label>
                    <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
                      <option value="plan">Plan</option>
                      <option value="pv_reception">PV de réception</option>
                      <option value="photo">Photo</option>
                      <option value="devis">Devis signé</option>
                      <option value="autre">Autre</option>
                    </select>
                  </div>
                  {champ('titre', 'Titre', { required: true })}
                </div>
                <div className="champ">
                  <label>Fichier (PDF, image, Word, Excel — 10 Mo max)</label>
                  <input type="file" required accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" onChange={(e) => setForm({ ...form, fichier: e.target.files[0] })} />
                </div>
              </>
            )}
            <div className="actions">
              <button type="button" className="secondaire" onClick={() => setModal(null)}>Annuler</button>
              <button type="submit">Enregistrer</button>
            </div>
          </form>
        </Modal>
      )}
    </>
  );
}
