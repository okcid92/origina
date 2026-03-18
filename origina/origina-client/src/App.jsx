import { useEffect, useState } from "react";
import "./App.css";

const reports = [
  {
    title: "Memoire_IA_M2.pdf",
    student: "Aline Mensah",
    date: "18 mars 2026",
    similarity: 12,
    status: "Analyse terminee",
  },
  {
    title: "Base_Donnees_Avancee.docx",
    student: "Jean Kouassi",
    date: "17 mars 2026",
    similarity: 34,
    status: "A verifier",
  },
  {
    title: "Reseaux_Securite.pdf",
    student: "Maya Soro",
    date: "16 mars 2026",
    similarity: 7,
    status: "Analyse terminee",
  },
];

function similarityClass(value) {
  if (value >= 25) return "pill pill-high";
  if (value >= 10) return "pill pill-medium";
  return "pill pill-low";
}

function App() {
  const [authUser, setAuthUser] = useState(() => {
    const raw = window.localStorage.getItem("origina_user");
    return raw ? JSON.parse(raw) : null;
  });
  const [email, setEmail] = useState("student1@origina.local");
  const [password, setPassword] = useState("password");
  const [loginLoading, setLoginLoading] = useState(false);
  const [loginError, setLoginError] = useState("");
  const [loading, setLoading] = useState(true);
  const [apiData, setApiData] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!authUser) {
      return;
    }

    async function checkApi() {
      try {
        setLoading(true);
        setError(null);

        const response = await fetch("/api/ping");

        if (!response.ok) {
          throw new Error("Reponse API invalide");
        }

        const data = await response.json();
        setApiData(data);
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    }

    checkApi();
  }, [authUser]);

  async function handleLogin(event) {
    event.preventDefault();
    setLoginLoading(true);
    setLoginError("");

    try {
      const response = await fetch("/api/login", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ email, password }),
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || "Echec de connexion.");
      }

      setAuthUser(data.user);
      window.localStorage.setItem("origina_user", JSON.stringify(data.user));
    } catch (err) {
      setLoginError(err.message || "Echec de connexion.");
    } finally {
      setLoginLoading(false);
    }
  }

  async function handleLogout() {
    try {
      await fetch("/api/logout", { method: "POST" });
    } catch {
      // Ignore logout API errors in local demo mode.
    }

    window.localStorage.removeItem("origina_user");
    setAuthUser(null);
    setApiData(null);
    setError(null);
  }

  if (!authUser) {
    return (
      <div className="login-page">
        <div className="login-card">
          <div className="login-brand">
            <div className="brand-dot" />
            <span>Origina</span>
          </div>
          <h1>Connexion</h1>
          <p className="login-subtitle">
            Accede au tableau de bord de detection de plagiat.
          </p>

          <form className="login-form" onSubmit={handleLogin}>
            <label>
              Email
              <input
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                placeholder="votre@email.com"
                required
              />
            </label>

            <label>
              Mot de passe
              <input
                type="password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                placeholder="********"
                required
              />
            </label>

            {loginError && <p className="error">{loginError}</p>}

            <button type="submit" disabled={loginLoading}>
              {loginLoading ? "Connexion..." : "Se connecter"}
            </button>
          </form>

          <div className="login-help">
            <p>Comptes de test:</p>
            <code>student1@origina.local / password</code>
            <code>teacher@origina.local / password</code>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="page">
      <header className="topbar">
        <div className="brand">
          <div className="brand-dot" />
          <span>Origina</span>
        </div>
        <nav className="topnav">
          <a href="#" className="active">
            Dashboard
          </a>
          <a href="#">Soumissions</a>
          <a href="#">Rapports</a>
          <a href="#">Parametres</a>
        </nav>
        <div className="topbar-user">
          <span>
            {authUser.name} ({authUser.role})
          </span>
          <button onClick={handleLogout}>Deconnexion</button>
        </div>
      </header>

      <main className="layout">
        <section className="hero card">
          <h1>Tableau de bord general</h1>
          <p>
            Vue globale de detection de plagiat pour Laravel + React + MySQL.
          </p>
          <div className="api-banner">
            {loading && <span>Verification API en cours...</span>}
            {!loading && error && (
              <span className="error">Backend indisponible: {error}</span>
            )}
            {!loading && !error && apiData && (
              <span className="success">
                API connectee - {apiData.message} ({apiData.timestamp})
              </span>
            )}
          </div>
        </section>

        <section className="kpi-grid">
          <article className="card kpi">
            <p>Soumissions totales</p>
            <h2>1,284</h2>
            <small>+6.1% ce mois</small>
          </article>
          <article className="card kpi">
            <p>Similarite moyenne</p>
            <h2>14.2%</h2>
            <small>-1.8% amelioration</small>
          </article>
          <article className="card kpi">
            <p>Cas a risque</p>
            <h2>97</h2>
            <small>priorite de revue</small>
          </article>
        </section>

        <section className="content-grid">
          <article className="card table-card">
            <div className="section-head">
              <h3>Rapports recents</h3>
              <button>Voir tout</button>
            </div>
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Document</th>
                    <th>Etudiant</th>
                    <th>Date</th>
                    <th>Similarite</th>
                    <th>Statut</th>
                  </tr>
                </thead>
                <tbody>
                  {reports.map((row) => (
                    <tr key={row.title}>
                      <td>{row.title}</td>
                      <td>{row.student}</td>
                      <td>{row.date}</td>
                      <td>
                        <span className={similarityClass(row.similarity)}>
                          {row.similarity}%
                        </span>
                      </td>
                      <td>{row.status}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </article>

          <aside className="side-column">
            <article className="card">
              <h3>Actions rapides</h3>
              <ul className="quick-actions">
                <li>Nouvelle soumission</li>
                <li>Lancer analyse groupee</li>
                <li>Exporter rapport PDF</li>
              </ul>
            </article>
            <article className="card">
              <h3>Integrite globale</h3>
              <div className="integrity-bar">
                <div style={{ width: "72%" }} />
              </div>
              <p className="muted">Score actuel: 92 / 100</p>
            </article>
          </aside>
        </section>
      </main>
    </div>
  );
}

export default App;
