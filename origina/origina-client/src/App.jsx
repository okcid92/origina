import { useEffect, useState } from "react";
import "./App.css";

function App() {
  const [loading, setLoading] = useState(true);
  const [apiData, setApiData] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
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
  }, []);

  return (
    <main className="container">
      <h1>Origina</h1>
      <p className="subtitle">Stack de depart: Laravel + React + MySQL</p>

      <section className="card">
        <h2>Etat de connexion API</h2>

        {loading && <p>Verification en cours...</p>}

        {!loading && error && (
          <p className="error">Impossible de joindre le backend: {error}</p>
        )}

        {!loading && !error && apiData && (
          <div className="success">
            <p>
              <strong>Statut:</strong> Connecte
            </p>
            <p>
              <strong>Message:</strong> {apiData.message}
            </p>
            <p>
              <strong>Horodatage:</strong> {apiData.timestamp}
            </p>
          </div>
        )}
      </section>

      <section className="card">
        <h2>Prochaines etapes</h2>
        <ol>
          <li>Configurer la base MySQL dans le fichier .env du backend</li>
          <li>Creer les migrations (themes, documents, rapports)</li>
          <li>Ajouter l'authentification (Sanctum/JWT)</li>
        </ol>
      </section>
    </main>
  );
}

export default App;
