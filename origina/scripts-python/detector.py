import sys
import json
import math
import re

# ---------------------------------------------------------
# MOTEUR DE DÉTECTION (Prototype de l'architecture Python)
# ---------------------------------------------------------

def extract_text(file_path):
    """Extraction raw : PyPDF2 (PDF) ou python-docx (Docx)."""
    return "Ceci est un exemple de mémoire d'étudiant pour analyser le plagiat logiciel."

def get_shingles(text, w=5):
    """
    Divise le texte en n-grammes de mots (Shingles).
    C'est la base de l'algorithme Shingling.
    """
    words = [word.lower() for word in re.findall(r'\w+', text)]
    return [" ".join(words[i:i+w]) for i in range(len(words)-w+1)]

def jaccard_similarity(shingles_a, shingles_b):
    """Compare mathématiquement les chevauchements (Local & Web)."""
    set_a = set(shingles_a)
    set_b = set(shingles_b)
    if not set_a: return 0.0
    return len(set_a.intersection(set_b)) / float(len(set_a.union(set_b)))

def analyze_local_shingle(text_document):
    """
    ÉTAPE 1 : Local Database.
    Analyse le document contre les archives de l'université (Thèmes & Mémoires).
    En production, on compresse les Shingles via MinHash / LSH pour des perfs instantanées.
    """
    # Simulation d'un document en base de données
    db_document = "Ceci est un exemple de mémoire d'étudiant sur la gestion d'anomalies logicielles."
    
    shingles_doc = get_shingles(text_document, w=3)
    shingles_db = get_shingles(db_document, w=3)
    
    score = jaccard_similarity(shingles_doc, shingles_db) * 100
    
    return {
        "score": round(score, 2),
        "matched_sources": ["Mémoire : Gestion d'anomalies logicielles (2023)"] if score > 0 else []
    }

def analyze_web_plagiarism(text_document):
    """
    ÉTAPE 2 : Internet.
    On découpe le document en expressions longues (phrases clés) et 
    on interroge une API Moteur de Recherche (par exemple Google Programmable Search).
    """
    # Simulation des résultats Web Scraping
    return {
        "score": 12.5,
        "matched_sources": ["https://fr.wikipedia.org/wiki/Memoire", "https://cours-univ.fr"]
    }

def analyze_ai_probability(text_document):
    """
    ÉTAPE 3 : Détection d'IA.
    (Linguistique, Style, Répétitions, Logique).
    Logique : un LLM a une "Perplexité" faible (ses phrases sont mathématiquement prévisibles).
    Un humain a une "Burstiness" élevée (phrases parfois très longues, parfois très courtes).
    Utiliser la librarie 'transformers' de HuggingFace en production (ex: RoBERTa).
    """
    # Simulation du comportement du modèle
    return {
        "score": 45.0, # 45% de chances d'avoir été généré par IA
        "indicators": "Perplexité constante, vocabulaire robotique."
    }

def main(file_path):
    text = extract_text(file_path)
    
    local = analyze_local_shingle(text)
    web = analyze_web_plagiarism(text)
    ai = analyze_ai_probability(text)
    
    # Agrégation des calculs
    global_score = round((local["score"] + web["score"] + ai["score"]) / 3, 2)
    
    output = {
        "metrics": {
            "local_shingle": local["score"],
            "web_search": web["score"],
            "ai_detection": ai["score"],
            "global_similarity": global_score
        },
        "sources": local["matched_sources"] + web["matched_sources"]
    }
    
    # L'Application Laravel capturera ce JSON via shell_exec !
    print(json.dumps(output))

if __name__ == "__main__":
    if len(sys.argv) > 1:
        main(sys.argv[1])
    else:
        main("test.pdf")
