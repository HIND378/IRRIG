import { Link } from 'react-router-dom';
import { ACCUEIL, useAuth } from '../auth';

/**
 * Page d'accueil publique : présentation d'IRRIG, de son activité et de ses offres.
 * Le bouton en haut à droite mène à la page de connexion (ou à l'espace de l'utilisateur déjà connecté).
 */

const ICONES = {
  goutte: <path d="M12 3c-3.5 5-6 8-6 11a6 6 0 0 0 12 0c0-3-2.5-6-6-11z" />,
  soleil: (
    <>
      <circle cx="12" cy="12" r="4" />
      <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
    </>
  ),
  capteur: (
    <>
      <path d="M12 21v-7" />
      <path d="M8.5 10.5a5 5 0 0 1 7 0M6 8a8.5 8.5 0 0 1 12 0" />
      <path d="M9 21h6" />
    </>
  ),
  outil: <path d="M14.7 6.3a4 4 0 0 0-5.4 5.2L4 16.8V20h3.2l5.3-5.3a4 4 0 0 0 5.2-5.4l-2.6 2.6-2.4-.6-.6-2.4z" />,
  carte: (
    <>
      <path d="M12 21s-6-5.5-6-11a6 6 0 0 1 12 0c0 5.5-6 11-6 11z" />
      <circle cx="12" cy="10" r="2" />
    </>
  ),
  telephone: <path d="M7 3h10v18H7zM11 18h2" />,
  graphe: <path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />,
  coeur: <path d="M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.5-7 10-7 10z" />,
};

function Icone({ nom }) {
  return (
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {ICONES[nom]}
    </svg>
  );
}

const OFFRES = [
  {
    icone: 'goutte',
    titre: 'Irrigation goutte-à-goutte',
    texte: "Des réseaux dimensionnés pour chaque parcelle : l'eau arrive au pied de la plante, sans gaspillage, pour les agrumes, le maraîchage, l'olivier ou les cultures sous serre.",
  },
  {
    icone: 'soleil',
    titre: 'Pompage solaire',
    texte: "Panneaux, variateurs et pompes immergées pour irriguer à partir d'un puits ou d'un bassin grâce à l'énergie du soleil, sans dépendre du réseau électrique.",
  },
  {
    icone: 'capteur',
    titre: 'Irrigation intelligente',
    texte: "Capteurs d'humidité du sol et programmateurs pour arroser au bon moment et dans la bonne quantité, selon les besoins réels de la culture.",
  },
  {
    icone: 'outil',
    titre: 'Installation, maintenance et assistance',
    texte: "Nos techniciens installent, entretiennent et dépannent les équipements sur le terrain, et vous accompagnent dans leur bonne utilisation.",
  },
];

const CLIENTS = [
  ['Petits exploitants', 'Des solutions simples et adaptées aux petites surfaces, avec un suivi de proximité.'],
  ['Coopératives agricoles', "Des installations collectives et un accompagnement pour l'ensemble des adhérents."],
  ['Entreprises agricoles', 'Des projets de taille moyenne, du dimensionnement à la maintenance des équipements.'],
];

const ETAPES = [
  ['Étude', 'Visite de la parcelle, analyse de la ressource en eau et des cultures.'],
  ['Conception', "Dimensionnement du réseau d'irrigation et du pompage, devis détaillé."],
  ['Installation', 'Pose des équipements et mise en service par nos techniciens.'],
  ['Suivi', 'Maintenance, dépannage et assistance tout au long de la vie des équipements.'],
];

const AGENCES = [
  ['Agadir', 'Souss-Massa'],
  ['Rabat', 'Rabat-Salé-Kénitra'],
  ['Casablanca', 'Casablanca-Settat'],
];

export default function Accueil() {
  const { user } = useAuth();

  return (
    <div className="accueil">
      <header className="acc-entete">
        <div className="acc-conteneur acc-barre">
          <a href="#haut" className="acc-logo">
            <span aria-hidden="true"><Icone nom="goutte" /></span> IRRIG
          </a>
          <nav className="acc-nav" aria-label="Sections de la page">
            <a href="#entreprise">L'entreprise</a>
            <a href="#offres">Nos offres</a>
            <a href="#clients">Nos clients</a>
            <a href="#agences">Nos agences</a>
          </nav>
          {user ? (
            <Link to={ACCUEIL[user.role]} className="bouton">Mon espace</Link>
          ) : (
            <Link to="/login" className="bouton">Connexion</Link>
          )}
        </div>
      </header>

      <section className="acc-hero" id="haut">
        <div className="acc-conteneur acc-hero-grille">
          <div>
            <p className="acc-surtitre">Solutions d'irrigation pour les zones rurales du Maroc</p>
            <h1>Chaque goutte compte, chaque récolte aussi.</h1>
            <p className="acc-intro">
              IRRIG conçoit, installe, entretient et commercialise des solutions d'irrigation pour les exploitations
              agricoles : goutte-à-goutte, pompage solaire et irrigation intelligente.
            </p>
            <div className="actions">
              <a href="#offres" className="bouton acc-bouton-clair">Découvrir nos offres</a>
              <Link to={user ? ACCUEIL[user.role] : '/login'} className="bouton acc-bouton-contour">
                {user ? 'Accéder à mon espace' : 'Espace client et collaborateurs'}
              </Link>
            </div>
          </div>
          <Illustration />
        </div>
      </section>

      <section className="acc-chiffres" aria-label="IRRIG en chiffres">
        <div className="acc-conteneur acc-chiffres-grille">
          {[
            ['2020', 'Année de création'],
            ['45', 'Collaborateurs'],
            ['3', 'Agences régionales'],
            ['600', 'Clients agricoles accompagnés'],
          ].map(([valeur, texte]) => (
            <div key={texte}>
              <strong>{valeur}</strong>
              <span>{texte}</span>
            </div>
          ))}
        </div>
      </section>

      <section className="acc-section" id="entreprise">
        <div className="acc-conteneur acc-deux-colonnes">
          <div>
            <p className="acc-surtitre">L'entreprise</p>
            <h2>Une PME marocaine au service de l'agriculture rurale</h2>
            <p>
              Créée en 2020, IRRIG est une PME marocaine spécialisée dans les solutions d'irrigation destinées aux
              exploitations agricoles en zones rurales. Notre métier couvre toute la chaîne : la conception des
              installations, leur commercialisation, leur installation sur le terrain et leur maintenance.
            </p>
            <p>
              Avec environ 45 collaborateurs répartis entre nos agences d'Agadir, de Rabat et de Casablanca, nous
              accompagnons près de 600 clients agricoles dans l'utilisation raisonnée de l'eau.
            </p>
          </div>
          <div className="acc-metiers">
            {[
              ['Conception', 'Étude des besoins et dimensionnement des installations.'],
              ['Commercialisation', 'Équipements adaptés à chaque exploitation et à chaque budget.'],
              ['Installation', 'Pose et mise en service par nos équipes techniques.'],
              ['Maintenance', 'Entretien préventif, dépannage et assistance technique.'],
            ].map(([titre, texte]) => (
              <div className="acc-metier" key={titre}>
                <h3>{titre}</h3>
                <p>{texte}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="acc-section acc-fond" id="offres">
        <div className="acc-conteneur">
          <p className="acc-surtitre">Nos offres</p>
          <h2>Des solutions complètes, de l'eau jusqu'à la plante</h2>
          <div className="acc-cartes">
            {OFFRES.map((o) => (
              <article className="acc-carte" key={o.titre}>
                <span className="acc-pastille"><Icone nom={o.icone} /></span>
                <h3>{o.titre}</h3>
                <p>{o.texte}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="acc-section">
        <div className="acc-conteneur">
          <p className="acc-surtitre">Notre démarche</p>
          <h2>Un accompagnement de bout en bout</h2>
          <ol className="acc-etapes">
            {ETAPES.map(([titre, texte], i) => (
              <li key={titre}>
                <span className="acc-numero">{i + 1}</span>
                <h3>{titre}</h3>
                <p>{texte}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <section className="acc-section acc-fond" id="clients">
        <div className="acc-conteneur">
          <p className="acc-surtitre">Nos clients</p>
          <h2>Pour toutes les tailles d'exploitation</h2>
          <div className="acc-cartes acc-cartes-3">
            {CLIENTS.map(([titre, texte]) => (
              <article className="acc-carte" key={titre}>
                <h3>{titre}</h3>
                <p>{texte}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="acc-section">
        <div className="acc-conteneur acc-deux-colonnes">
          <div>
            <p className="acc-surtitre">IRRIG Connect</p>
            <h2>Un service plus proche grâce au digital</h2>
            <p>
              Notre plateforme IRRIG Connect relie nos clients, nos agences et nos techniciens pour un suivi plus
              rapide et plus transparent.
            </p>
            <Link to={user ? ACCUEIL[user.role] : '/login'} className="bouton">
              {user ? 'Accéder à mon espace' : 'Se connecter'}
            </Link>
          </div>
          <ul className="acc-avantages">
            {[
              ['telephone', 'Portail client en arabe et en français', "Demandez une intervention, suivez-la et donnez votre avis depuis votre téléphone."],
              ['carte', 'Techniciens équipés sur le terrain', 'Une application mobile qui fonctionne même dans les zones sans réseau.'],
              ['graphe', 'Suivi de vos équipements', 'Historique des installations, des garanties et des interventions.'],
              ['coeur', "À l'écoute de votre satisfaction", 'Chaque intervention peut être évaluée pour améliorer notre service.'],
            ].map(([icone, titre, texte]) => (
              <li key={titre}>
                <span className="acc-pastille"><Icone nom={icone} /></span>
                <div>
                  <h3>{titre}</h3>
                  <p>{texte}</p>
                </div>
              </li>
            ))}
          </ul>
        </div>
      </section>

      <section className="acc-section acc-fond" id="agences">
        <div className="acc-conteneur">
          <p className="acc-surtitre">Nos agences</p>
          <h2>Trois agences régionales, au plus près des exploitations</h2>
          <div className="acc-cartes acc-cartes-3">
            {AGENCES.map(([ville, region]) => (
              <article className="acc-carte acc-agence" key={ville}>
                <span className="acc-pastille"><Icone nom="carte" /></span>
                <div>
                  <h3>Agence de {ville}</h3>
                  <p>Région {region}</p>
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>

      <footer className="acc-pied">
        <div className="acc-conteneur acc-pied-grille">
          <div>
            <div className="acc-logo"><span aria-hidden="true"><Icone nom="goutte" /></span> IRRIG</div>
            <p>Solutions d'irrigation pour les zones rurales du Maroc.</p>
          </div>
          <p className="acc-mention">© {new Date().getFullYear()} IRRIG — entreprise fictive réalisée dans le cadre d'un TP de digitalisation.</p>
        </div>
      </footer>
    </div>
  );
}

/** Illustration : parcelle irriguée au goutte-à-goutte, alimentée par une pompe solaire. */
function Illustration() {
  return (
    <svg className="acc-illustration" viewBox="0 0 420 320" role="img" aria-label="Parcelle irriguée au goutte-à-goutte alimentée par un panneau solaire">
      <circle cx="340" cy="70" r="34" fill="#f4c95d" />
      <path d="M0 220 Q 210 170 420 220 V320 H0z" fill="#3f8f5f" />
      <path d="M0 250 Q 210 205 420 250 V320 H0z" fill="#2f7a4f" />
      <g transform="translate(40 120)">
        <rect x="0" y="0" width="92" height="56" rx="4" fill="#1d4f8a" transform="skewX(-12)" />
        <path d="M10 18h72M8 37h72M30 0l-10 56M58 0l-10 56" stroke="#5f93cf" strokeWidth="2" transform="skewX(-12)" />
        <rect x="32" y="56" width="8" height="52" fill="#d9e3dc" />
      </g>
      <path d="M80 230 C 160 214 260 214 400 232" stroke="#9fd3f2" strokeWidth="5" fill="none" strokeLinecap="round" />
      <path d="M60 262 C 160 240 270 240 410 262" stroke="#9fd3f2" strokeWidth="5" fill="none" strokeLinecap="round" />
      {[120, 180, 240, 300, 360].map((x, i) => (
        <g key={x}>
          <path d={`M${x} ${222 - i * 0.5}c-6-14-2-26 0-34c2 8 6 20 0 34z`} fill="#bfe6a8" />
          <path d={`M${x} ${222 - i * 0.5}c-14-8-18-18-18-24c8 2 16 10 18 24z`} fill="#a6d98b" />
          <circle cx={x} cy={232 - i * 0.6 + 8} r="3.2" fill="#e6f5ff" />
        </g>
      ))}
    </svg>
  );
}
