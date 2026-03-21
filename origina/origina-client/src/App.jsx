import { useEffect, useMemo, useState } from "react";
import "./App.css";

const authMessage = "Utilisateur non authentifie. Fournir X-User-Id.";

function toPercent(value) {
  return `${Number(value || 0).toFixed(2)}%`;
}

function riskClass(level) {
  if (level === "high") return "pill pill-high";
  if (level === "medium") return "pill pill-medium";
  return "pill pill-low";
}

function App() {
  const [authUser, setAuthUser] = useState(() => {
    const raw = window.localStorage.getItem("origina_user");
    return raw ? JSON.parse(raw) : null;
  });
  const [email, setEmail] = useState("student1@origina.local");
  const [password, setPassword] = useState("mon926732");
  const [loginLoading, setLoginLoading] = useState(false);
  const [loginError, setLoginError] = useState("");

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const [overview, setOverview] = useState({
    role: "",
    themes: [],
    documents: [],
    reports: [],
  });
  const [pendingThemes, setPendingThemes] = useState([]);
  const [reports, setReports] = useState([]);
  const [selectedReport, setSelectedReport] = useState(null);
  const [reportDetails, setReportDetails] = useState(null);

  const [newThemeTitle, setNewThemeTitle] = useState("");
  const [newThemeDescription, setNewThemeDescription] = useState("");
  const [uploadThemeId, setUploadThemeId] = useState("");
  const [uploadName, setUploadName] = useState("memoire-v2.pdf");
  const [lastAutoTest, setLastAutoTest] = useState(null);
  const [decisionNotes, setDecisionNotes] = useState("");

  async function apiFetch(path, options = {}) {
    const headers = {
      "Content-Type": "application/json",
      ...(options.headers || {}),
    };

    if (authUser?.id) {
      headers["X-User-Id"] = String(authUser.id);
    }

    const response = await fetch(path, { ...options, headers });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(data.message || "Erreur API");
    }

    return data;
  }

  async function reloadData() {
    if (!authUser) return;

    try {
      setLoading(true);
      setError("");
      const data = await apiFetch("/api/me/overview");
      setOverview(data);

      if (["teacher", "admin"].includes(authUser.role)) {
        const pending = await apiFetch("/api/themes/pending");
        setPendingThemes(pending.themes || []);
      } else {
        setPendingThemes([]);
      }

      if (["teacher", "admin", "da", "var"].includes(authUser.role)) {
        const allReports = await apiFetch("/api/reports");
        setReports(allReports.reports || []);
      } else {
        setReports([]);
      }
    } catch (err) {
      setError(err.message);
      if (err.message === authMessage) {
        handleLogout();
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    reloadData();
  }, [authUser]);

  async function handleLogin(event) {
    event.preventDefault();
    setLoginLoading(true);
    setLoginError("");

    try {
      const response = await fetch("/api/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password }),
      });
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || "Echec de connexion.");
      }

      setAuthUser(data.user);
      window.localStorage.setItem("origina_user", JSON.stringify(data.user));
      setNotice("");
      setError("");
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
      // No-op.
    }

    window.localStorage.removeItem("origina_user");
    setAuthUser(null);
    setOverview({ role: "", themes: [], documents: [], reports: [] });
    setPendingThemes([]);
    setReports([]);
    setSelectedReport(null);
    setReportDetails(null);
    setNotice("");
    setError("");
  }

  async function proposeTheme(event) {
    event.preventDefault();
    setNotice("");
    setError("");

    try {
      const payload = await apiFetch("/api/themes/propose", {
        method: "POST",
        body: JSON.stringify({
          title: newThemeTitle,
          description: newThemeDescription,
        }),
      });

      setNotice(payload.message);
      setNewThemeTitle("");
      setNewThemeDescription("");
      await reloadData();
    } catch (err) {
      setError(err.message);
    }
  }

  async function uploadDocument(event) {
    event.preventDefault();
    setNotice("");
    setError("");

    try {
      const payload = await apiFetch("/api/documents/upload", {
        method: "POST",
        body: JSON.stringify({
          theme_id: Number(uploadThemeId),
          original_name: uploadName,
          mime_type: "application/pdf",
          file_size: 1843200,
          is_final: true,
        }),
      });

      setNotice(payload.message);
      await reloadData();
    } catch (err) {
      setError(err.message);
    }
  }

  async function launchAutoTest(documentId) {
    setNotice("");
    setError("");

    try {
      const payload = await apiFetch(`/api/documents/${documentId}/auto-test`, {
        method: "POST",
      });
      setLastAutoTest(payload.metrics);
      setNotice(payload.message);
    } catch (err) {
      setError(err.message);
    }
  }

  async function moderateTheme(themeId, decision) {
    setNotice("");
    setError("");

    try {
      const payload = await apiFetch(`/api/themes/${themeId}/moderate`, {
        method: "PATCH",
        body: JSON.stringify({
          decision,
          comment:
            decision === "approved"
              ? "Theme valide pour progression."
              : "Theme a reformuler.",
        }),
      });
      setNotice(payload.message);
      await reloadData();
    } catch (err) {
      setError(err.message);
    }
  }

  async function launchAnalysis(documentId) {
    setNotice("");
    setError("");

    try {
      const payload = await apiFetch(`/api/documents/${documentId}/analyze`, {
        method: "POST",
        body: JSON.stringify({
          include_direct: true,
          include_paraphrase: true,
          include_translation: true,
          include_ai: true,
        }),
      });

      setNotice(payload.message);
      await reloadData();
    } catch (err) {
      setError(err.message);
    }
  }

  async function openReport(reportId) {
    setSelectedReport(reportId);
    setNotice("");
    setError("");

    try {
      const data = await apiFetch(`/api/reports/${reportId}`);
      setReportDetails(data);
    } catch (err) {
      setError(err.message);
      setReportDetails(null);
    }
  }

  async function deliberate(decision) {
    if (!selectedReport) {
      setError("Choisis d abord un rapport.");
      return;
    }

    setNotice("");
    setError("");

    try {
      const payload = await apiFetch(
        `/api/reports/${selectedReport}/deliberate`,
        {
          method: "POST",
          body: JSON.stringify({ decision, notes: decisionNotes }),
        },
      );
      setNotice(payload.message);
      setDecisionNotes("");
      await reloadData();
    } catch (err) {
      setError(err.message);
    }
  }

  const approvedThemes = useMemo(
    () =>
      (overview.themes || []).filter((theme) => theme.status === "approved"),
    [overview.themes],
  );

  if (!authUser) {
    return (
      <div className="login-page">
        <nav className="lp-nav">
          <div className="lp-nav-inner">
            <div className="lp-brand">Origina</div>
            <div className="lp-nav-links">
              <a href="#">Solutions</a>
              <a href="#">AI Detection</a>
              <a href="#">Academic Tools</a>
              <a href="#">Tarification</a>
            </div>
            <a className="lp-nav-cta" href="#login-panel">
              Connexion
            </a>
          </div>
        </nav>

        <main className="lp-main">
          <section className="lp-hero">
            <div className="lp-hero-copy">
              <span className="lp-chip">Integrite Academique 2.0</span>
              <h1>
                Garantissez l Integrite
                <span> Academique</span>.
              </h1>
              <p>
                Une plateforme complete pour la soumission, l analyse
                anti-plagiat et la deliberation institutionnelle.
              </p>
              <div className="lp-hero-actions">
                <a href="#login-panel">Commencer l analyse</a>
                <button type="button">Decouvrir nos modules</button>
              </div>
            </div>

            <aside className="lp-login-card" id="login-panel">
              <h2>Connexion</h2>
              <p>Accede a ton espace Etudiant, Enseignant ou DA/VAR.</p>

              <form className="login-form" onSubmit={handleLogin}>
                <label>
                  Email
                  <input
                    type="email"
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    placeholder="nom@origina.local"
                    required
                  />
                </label>

                <label>
                  Mot de passe
                  <input
                    type="password"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    placeholder="Votre mot de passe"
                    required
                  />
                </label>

                {loginError && <p className="error">{loginError}</p>}

                <button type="submit" disabled={loginLoading}>
                  {loginLoading ? "Connexion..." : "Se connecter"}
                </button>
              </form>

              <div className="login-help">
                <p>Comptes demo (mot de passe: mon926732)</p>
                <code>student1@origina.local</code>
                <code>teacher@origina.local</code>
                <code>da@origina.local</code>
                <code>var@origina.local</code>
              </div>
            </aside>
          </section>

          <section className="lp-bento">
            <h3>Excellence Analytique</h3>
            <div className="lp-bento-grid">
              <article className="wide">
                <h4>Moteur Multi-Niveaux</h4>
                <p>
                  Detection du plagiat direct, paraphrase et traduction dans un
                  moteur unifie.
                </p>
                <div className="lp-tags">
                  <span>Direct</span>
                  <span>Paraphrase</span>
                  <span>Traduction</span>
                </div>
              </article>
              <article>
                <h4>Analyse IA</h4>
                <p>
                  Detection des contenus generes par IA avec score de risque.
                </p>
              </article>
              <article>
                <h4>Gestion des Roles</h4>
                <p>Flux dedies pour Etudiant, Enseignant et Commission.</p>
              </article>
              <article className="wide">
                <h4>Rapports Detailes</h4>
                <p>
                  Segments surlignes, sources detectees et visualisations pour
                  decision rapide.
                </p>
              </article>
            </div>
          </section>

          <section className="lp-why">
            <h3>Pourquoi Origina ?</h3>
            <p>
              Concu pour les exigences academiques elevees, Origina structure le
              cycle complet: theme, soumission, analyse, deliberation.
            </p>
            <ul>
              <li>Conformite pedagogique et processus traceable.</li>
              <li>Protection de la reputation institutionnelle.</li>
              <li>Pilotage rapide des cas a risque.</li>
            </ul>
          </section>

          <section className="lp-cta">
            <h3>Pret a elever vos standards academiques ?</h3>
            <a href="#login-panel">Rejoindre Origina</a>
          </section>
        </main>

        <footer className="lp-footer">
          <span>Origina Strategic Systems</span>
          <small>© 2026 Tous droits reserves.</small>
        </footer>
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
        <div className="topbar-user">
          <span>
            {authUser.name} ({authUser.role})
          </span>
          <button onClick={handleLogout}>Deconnexion</button>
        </div>
      </header>

      <main className="layout">
        <section className="hero card">
          <h1>Flux metier par role</h1>
          <p>
            Authentification, proposition de theme, moderation, analyses
            multi-types, rapport et deliberation DA/VAR.
          </p>
          {loading && <p className="muted">Chargement...</p>}
          {error && <p className="error">{error}</p>}
          {notice && <p className="success">{notice}</p>}
        </section>

        {authUser.role === "student" && (
          <section className="content-grid">
            <article className="card table-card">
              <h3>1) Proposer un theme</h3>
              <form className="stack-form" onSubmit={proposeTheme}>
                <input
                  value={newThemeTitle}
                  onChange={(event) => setNewThemeTitle(event.target.value)}
                  placeholder="Titre du theme"
                  required
                />
                <textarea
                  rows="3"
                  value={newThemeDescription}
                  onChange={(event) =>
                    setNewThemeDescription(event.target.value)
                  }
                  placeholder="Description courte"
                />
                <button type="submit">Soumettre le theme</button>
              </form>

              <h3>2) Themes proposes</h3>
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Theme</th>
                      <th>Statut</th>
                      <th>Commentaire</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(overview.themes || []).map((theme) => (
                      <tr key={theme.id}>
                        <td>{theme.title}</td>
                        <td>{theme.status}</td>
                        <td>{theme.moderation_comment || "-"}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </article>

            <aside className="side-column">
              <article className="card">
                <h3>3) Televerser le memoire</h3>
                <form className="stack-form" onSubmit={uploadDocument}>
                  <select
                    value={uploadThemeId}
                    onChange={(event) => setUploadThemeId(event.target.value)}
                    required
                  >
                    <option value="">Choisir un theme valide</option>
                    {approvedThemes.map((theme) => (
                      <option key={theme.id} value={theme.id}>
                        {theme.title}
                      </option>
                    ))}
                  </select>
                  <input
                    value={uploadName}
                    onChange={(event) => setUploadName(event.target.value)}
                    placeholder="Nom du fichier"
                    required
                  />
                  <button type="submit">Televerser</button>
                </form>
              </article>

              <article className="card">
                <h3>4) Auto-test plagiat</h3>
                <ul className="quick-actions">
                  {(overview.documents || []).map((doc) => (
                    <li key={doc.id}>
                      <button
                        className="line-button"
                        onClick={() => launchAutoTest(doc.id)}
                      >
                        Auto-test {doc.original_name}
                      </button>
                    </li>
                  ))}
                </ul>

                {lastAutoTest && (
                  <div className="metrics-box">
                    <p>Direct: {toPercent(lastAutoTest.direct_plagiarism)}</p>
                    <p>Paraphrase: {toPercent(lastAutoTest.paraphrase)}</p>
                    <p>Traduction: {toPercent(lastAutoTest.translation)}</p>
                    <p>IA: {toPercent(lastAutoTest.ai_detection)}</p>
                    <p>Global: {toPercent(lastAutoTest.global_similarity)}</p>
                  </div>
                )}
              </article>
            </aside>
          </section>
        )}

        {["teacher", "admin"].includes(authUser.role) && (
          <section className="content-grid">
            <article className="card table-card">
              <h3>Moderation des themes</h3>
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Etudiant</th>
                      <th>Theme</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {pendingThemes.map((theme) => (
                      <tr key={theme.id}>
                        <td>{theme.student_name}</td>
                        <td>{theme.title}</td>
                        <td className="actions">
                          <button
                            onClick={() => moderateTheme(theme.id, "approved")}
                          >
                            Valider
                          </button>
                          <button
                            className="ghost"
                            onClick={() => moderateTheme(theme.id, "rejected")}
                          >
                            Rejeter
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </article>

            <aside className="side-column">
              <article className="card">
                <h3>Lancer analyse de plagiat</h3>
                <ul className="quick-actions">
                  {(overview.documents || []).map((doc) => (
                    <li key={doc.id}>
                      <button
                        className="line-button"
                        onClick={() => launchAnalysis(doc.id)}
                      >
                        {doc.student_name} - {doc.original_name}
                      </button>
                    </li>
                  ))}
                </ul>
              </article>

              <article className="card">
                <h3>Consulter rapports</h3>
                <ul className="quick-actions">
                  {reports.map((row) => (
                    <li key={row.id}>
                      <button
                        className="line-button"
                        onClick={() => openReport(row.id)}
                      >
                        Rapport #{row.id} - {row.student_name}
                      </button>
                    </li>
                  ))}
                </ul>
              </article>
            </aside>
          </section>
        )}

        {["da", "var"].includes(authUser.role) && (
          <section className="content-grid">
            <article className="card table-card">
              <h3>Dossiers a statuer</h3>
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Etudiant</th>
                      <th>Theme</th>
                      <th>Similarite</th>
                      <th>Risque</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {reports.map((row) => (
                      <tr key={row.id}>
                        <td>{row.student_name}</td>
                        <td>{row.theme_title}</td>
                        <td>{toPercent(row.global_similarity)}</td>
                        <td>
                          <span className={riskClass(row.risk_level)}>
                            {row.risk_level}
                          </span>
                        </td>
                        <td>
                          <button onClick={() => openReport(row.id)}>
                            Voir rapport
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </article>

            <aside className="side-column">
              <article className="card">
                <h3>Decision Commission</h3>
                <textarea
                  rows="4"
                  value={decisionNotes}
                  onChange={(event) => setDecisionNotes(event.target.value)}
                  placeholder="Notes de deliberation"
                />
                <div className="button-row">
                  <button onClick={() => deliberate("final_validation")}>
                    Validation finale
                  </button>
                  <button
                    className="ghost"
                    onClick={() => deliberate("sanction")}
                  >
                    Sanction
                  </button>
                  <button
                    className="ghost"
                    onClick={() => deliberate("rewrite_required")}
                  >
                    Reecriture
                  </button>
                </div>
              </article>
            </aside>
          </section>
        )}

        {reportDetails && (
          <section className="card table-card">
            <h3>Rapport detaille #{reportDetails.report.id}</h3>
            <p>
              {reportDetails.report.student_name} -{" "}
              {reportDetails.report.theme_title} - similarite{" "}
              {toPercent(reportDetails.report.global_similarity)}
            </p>

            <div className="graphs-grid">
              <div>
                <h4>Analyse</h4>
                {(reportDetails.graphs.analysis_breakdown || []).map((item) => (
                  <div key={item.label} className="graph-row">
                    <span>{item.label}</span>
                    <div className="integrity-bar">
                      <div
                        style={{
                          width: `${Math.min(100, Number(item.value || 0))}%`,
                        }}
                      />
                    </div>
                    <strong>{toPercent(item.value)}</strong>
                  </div>
                ))}
              </div>

              <div>
                <h4>Sources</h4>
                {(reportDetails.graphs.sources_distribution || []).map(
                  (item) => (
                    <div key={item.label} className="graph-row">
                      <span>{item.label}</span>
                      <div className="integrity-bar">
                        <div
                          style={{
                            width: `${Math.min(100, Number(item.value || 0) * 4)}%`,
                          }}
                        />
                      </div>
                      <strong>{toPercent(item.value)}</strong>
                    </div>
                  ),
                )}
              </div>
            </div>
          </section>
        )}
      </main>
    </div>
  );
}

export default App;
