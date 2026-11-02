import { useEffect, useState } from 'react';
import api, { messageErreur } from '../api';
import { Message, Modal } from '../components/ui';

/** Gestion des agences (directeur multi-agences). */
export default function Agences() {
  const [agences, setAgences] = useState([]);
  const [form, setForm] = useState(null);
  const [erreur, setErreur] = useState('');

  const charger = () => api.get('/agences').then(({ data }) => setAgences(data)).catch((e) => setErreur(messageErreur(e)));

  useEffect(() => {
    charger();
  }, []);

  async function enregistrer(e) {
    e.preventDefault();
    try {
      if (form.id) await api.put(`/agences/${form.id}`, form);
      else await api.post('/agences', form);
      setForm(null);
      charger();
    } catch (err) {
      setErreur(messageErreur(err));
    }
  }

  return (
    <>
      <div className="entete">
        <div>
          <h1>Agences</h1>
          <p className="sous-titre">Une nouvelle agence reçoit automatiquement le catalogue de pièces (stock à zéro).</p>
        </div>
        <button onClick={() => { setErreur(''); setForm({ ville: '', adresse: '', telephone: '' }); }}>+ Nouvelle agence</button>
      </div>
      {!form && <Message erreur={erreur} />}
      <div className="grille grille-4">
        {agences.map((a) => (
          <div className="carte" key={a.id}>
            <h2>{a.ville}</h2>
            <p className="sous-titre">{a.adresse}<br />{a.telephone}</p>
            <p>{a.clients_count} clients · {a.utilisateurs_count} collaborateurs</p>
            <button className="secondaire petit" onClick={() => { setErreur(''); setForm(a); }}>Modifier</button>
          </div>
        ))}
      </div>

      {form && (
        <Modal titre={form.id ? `Modifier ${form.ville}` : 'Nouvelle agence'} onFermer={() => setForm(null)}>
          <form onSubmit={enregistrer}>
            <Message erreur={erreur} />
            {['ville', 'adresse', 'telephone'].map((c) => (
              <div className="champ" key={c}>
                <label>{{ ville: 'Ville', adresse: 'Adresse', telephone: 'Téléphone' }[c]}</label>
                <input value={form[c] ?? ''} onChange={(e) => setForm({ ...form, [c]: e.target.value })} required={c === 'ville'} />
              </div>
            ))}
            <div className="actions">
              <button type="button" className="secondaire" onClick={() => setForm(null)}>Annuler</button>
              <button type="submit">Enregistrer</button>
            </div>
          </form>
        </Modal>
      )}
    </>
  );
}
