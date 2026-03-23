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
  const [theme, setTheme] = useState(() => {
    return window.localStorage.getItem("origina_theme") || "dark";
  });

  useEffect(() => {
    document.documentElement.setAttribute("data-theme", theme);
    window.localStorage.setItem("origina_theme", theme);
  }, [theme]);

  function toggleTheme() {
    setTheme((prev) => (prev === "dark" ? "light" : "dark"));
  }

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
  const [uploadFile, setUploadFile] = useState(null);
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

      if (["teacher", "admin", "da"].includes(authUser.role)) {
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
    if (!uploadFile) {
      setError("Veuillez sélectionner un document.");
      return;
    }
    setNotice("");
    setError("");

    try {
      const payload = await apiFetch("/api/documents/upload", {
        method: "POST",
        body: JSON.stringify({
          theme_id: Number(uploadThemeId),
          original_name: uploadFile.name,
          mime_type: uploadFile.type || "application/pdf",
          file_size: uploadFile.size,
          is_final: true,
        }),
      });

      setNotice(payload.message);
      setUploadFile(null); // Reset the file after upload
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
    let noteDa = undefined;
    if (authUser.role === "da" && decision === "approved") {
      const input = window.prompt("Entrez la note finale ou appréciation (Note_DA) pour ce thème :");
      if (input === null) return;
      noteDa = input;
    }

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
          note_da: noteDa
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
      (overview.themes || []).filter((theme) => theme.status === "VALIDATED_DA"),
    [overview.themes],
  );

  if (!authUser) {
    return (
      <div className="login-page">
        <nav className="lp-nav">
          <div className="lp-nav-inner">
            <div className="lp-brand">Origina</div>
            {/* <div className="lp-nav-links">
              <a href="#">Solutions</a>
              <a href="#">AI Detection</a>
              <a href="#">Academic Tools</a>
              <a href="#">Tarification</a>
            </div> */}
            <div className="lp-nav-actions" style={{ display: 'flex', gap: '16px', alignItems: 'center' }}>
              <button 
                onClick={toggleTheme} 
                className="theme-toggle" 
                aria-label="Changer le thème"
              >
                {theme === "dark" ? (
                  <span className="material-symbols-outlined text-lg">light_mode</span>
                ) : (
                  <span className="material-symbols-outlined text-lg">dark_mode</span>
                )}
              </button>
              {/* <a className="lp-nav-cta" href="#login-panel">
                Connexion
              </a> */}
            </div>
          </div>
        </nav>

        <main className="lp-main">
          <div className="lp-hero-copy">
            <h1>
              Garantissez l'Intégrité
              <span> Académique</span>.
            </h1>
            <p>
              Une plateforme complète pour la soumission, l'analyse
              anti-plagiat et la délibération institutionnelle.
            </p>
            {/* <div className="lp-hero-actions">
              <a href="#login-panel">Commencer l'analyse</a>
            </div> */}
          </div>

          <aside className="lp-login-card" id="login-panel">
            <h2>Connexion</h2>
            <p>Accédez à votre espace Étudiant, Enseignant ou DA/VAR.</p>

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
              <p>Comptes démo (mot de passe: mon926732)</p>
              <code>student1@origina.local</code>
              <code>teacher@origina.local</code>
              <code>da@origina.local</code>
              <code>var@origina.local</code>
            </div>
          </aside>
        </main>
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
          <button 
            onClick={toggleTheme} 
            className="theme-toggle" 
            aria-label="Changer le thème"
          >
            {theme === "dark" ? (
              <span className="material-symbols-outlined text-lg">light_mode</span>
            ) : (
              <span className="material-symbols-outlined text-lg">dark_mode</span>
            )}
          </button>
          <span>
            {authUser.name} ({authUser.role})
          </span>
          <button onClick={handleLogout}>Déconnexion</button>
        </div>
      </header>

      <main className="layout">
        <section className="hero card">
          <h1>Tableau de bord</h1>
          <p>
            Actions rapides pour {authUser.role}.
          </p>
          {loading && <p className="muted">Chargement...</p>}
          {error && <p className="error">{error}</p>}
          {notice && <p className="success">{notice}</p>}
        </section>

        {authUser.role === "student" && (
          <div className="content-grid">
            <article className="card table-card">
              <h3>1) Proposer un thème</h3>
              <form className="stack-form" onSubmit={proposeTheme}>
                <input
                  value={newThemeTitle}
                  onChange={(event) => setNewThemeTitle(event.target.value)}
                  placeholder="Titre du thème"
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
                <button type="submit">Soumettre le thème</button>
              </form>

              <h3>2) Thèmes proposés</h3>
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Thème</th>
                      <th>Statut</th>
                      <th>Commentaire Chef/DA</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(overview.themes || []).map((theme) => (
                      <tr key={theme.id}>
                        <td>{theme.title}</td>
                        <td>
                           {theme.status === "PENDING" && <span className="pill pill-medium">En attente (Chef Dpt)</span>}
                           {theme.status === "VALIDATED_CD" && <span className="pill pill-medium">En attente (DA)</span>}
                           {theme.status === "VALIDATED_DA" && <span className="pill pill-low">Validé DA</span>}
                           {theme.status === "REJECTED" && <span className="pill pill-high">Rejeté</span>}
                        </td>
                        <td>{theme.note_da ? `Note DA: ${theme.note_da}` : (theme.moderation_comment || "-")}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </article>

            <aside className="side-column">
              <article className="card">
                <h3>3) Téléverser le mémoire</h3>
                <form className="stack-form" onSubmit={uploadDocument}>
                  <select
                    value={uploadThemeId}
                    onChange={(event) => setUploadThemeId(event.target.value)}
                    required
                  >
                    <option value="">Choisir un thème (Validé par le DA)</option>
                    {approvedThemes.map((theme) => (
                      <option key={theme.id} value={theme.id}>
                        {theme.title}
                      </option>
                    ))}
                  </select>
                  <input
                    type="file"
                    accept=".pdf,.doc,.docx"
                    onChange={(event) => {
                      if (event.target.files && event.target.files.length > 0) {
                        setUploadFile(event.target.files[0]);
                      }
                    }}
                    required
                    style={{ fontSize: "0.875rem", padding: "8px" }}
                  />
                  <button type="submit">Téléverser le fichier</button>
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
                    <p><span>Local (Shingle Algo)</span> <strong>{toPercent(lastAutoTest.local_shingle)}</strong></p>
                    <p><span>Web (Moteurs Recherche)</span> <strong>{toPercent(lastAutoTest.web_search)}</strong></p>
                    <p><span>IA (Linguistique, Logique)</span> <strong>{toPercent(lastAutoTest.ai_detection)}</strong></p>
                    <p><span>Similarité Globale</span> <strong>{toPercent(lastAutoTest.global_similarity)}</strong></p>
                  </div>
                )}
              </article>
            </aside>
          </div>
        )}

        {["teacher", "admin", "da"].includes(authUser.role) && (
          <div className="content-grid" style={{ marginBottom: "24px" }}>
            <article className="card table-card">
              <h3>Modération des thèmes proposés {authUser.role === "da" && "(Validation Finale)"}</h3>
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Étudiant</th>
                      <th>Thème</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {pendingThemes.length === 0 && (
                      <tr><td colSpan="3" className="muted">Aucun thème en attente.</td></tr>
                    )}
                    {pendingThemes.map((theme) => (
                      <tr key={theme.id}>
                        <td>{theme.student_name}</td>
                        <td>{theme.title}</td>
                        <td className="actions">
                          <button
                            onClick={() => moderateTheme(theme.id, "approved")}
                          >
                            {authUser.role === "da" ? "Valider & Noter" : "Transmettre DA"}
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
          </div>
        )}

        {["teacher", "admin"].includes(authUser.role) && (
          <div className="content-grid">
            <aside className="side-column" style={{ gridColumn: '1 / span 2', display: 'grid', gridTemplateColumns: '1fr 1fr' }}>
              <article className="card">
                <h3>Lancer analyse de plagiat multi-niveaux</h3>
                <p className="muted" style={{ marginBottom: "16px" }}>Local (Shingle), IA et Web.</p>
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
          </div>
        )}

        {["da", "var"].includes(authUser.role) && (
          <div className="content-grid">
            <article className="card table-card">
              <h3>Dossiers à statuer</h3>
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Étudiant</th>
                      <th>Thème</th>
                      <th>Similarité</th>
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
                <h3>Décision Commission</h3>
                <textarea
                  rows="4"
                  value={decisionNotes}
                  onChange={(event) => setDecisionNotes(event.target.value)}
                  placeholder="Notes de délibération"
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
                    Réécriture
                  </button>
                </div>
              </article>
            </aside>
          </div>
        )}

        {reportDetails && (
          <section className="card table-card">
            <h3>Rapport détaillé #{reportDetails.report.id}</h3>
            <p>
              {reportDetails.report.student_name} -{" "}
              {reportDetails.report.theme_title} - similarité{" "}
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
