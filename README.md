# Gestion PFE : plateforme de gestion des stages de fin d'études

Application web en **PHP natif**, **MySQL** et **Bootstrap 5** pour centraliser, suivre et valider les stages PFE.

## Installation (Laragon)

1. Démarrer Laragon (Apache + MySQL).
2. Importer `database/gestion_pfe.sql` (HeidiSQL : *Fichier > Exécuter un fichier SQL*, ou phpMyAdmin > Importer).
   Le script crée la base `gestion_pfe` avec des données de démonstration.
3. Vérifier les identifiants MySQL dans `config/config.php` (par défaut `root` sans mot de passe).
4. Ouvrir http://localhost/GestionPFE/ (ou http://gestionpfe.test/).

## Comptes de démonstration (mot de passe : `password123`)

| Rôle | Email |
|---|---|
| Administrateur | admin@pfe.tn |
| Encadrant | encadrant@pfe.tn, amel@pfe.tn |
| Étudiant | etudiant@pfe.tn, ines@pfe.tn |

## Workflow de validation

```
Brouillon -> Soumis -> Validé par encadrant -> Validé (en cours) -> Terminé
                |  ^
                v  |  (corrections demandées par l'encadrant)
             À corriger
Refus possible par l'encadrant (étape « Soumis ») ou par l'administrateur.
```

* **Étudiant** : inscription, dépôt du stage (sujet, entreprise, dates, convention PDF), soumission,
  journal de suivi avec % d'avancement, dépôt du rapport PDF.
* **Encadrant** : liste des étudiants encadrés, validation / demande de corrections / refus du sujet,
  remarques dans le suivi, évaluation finale (note /20) et clôture.
* **Administrateur** : tableau de bord statistique, affectation des encadrants, validation finale,
  gestion des utilisateurs et des entreprises, export CSV des stages.

Toutes les étapes sont tracées dans l'historique et notifient les personnes concernées.

## Structure

```
config/      configuration et connexion PDO
includes/    fonctions communes, en-tête, pied de page, détail d'un stage
etudiant/    espace étudiant
encadrant/   espace encadrant
admin/       espace administrateur
assets/      CSS / JS
uploads/     documents PDF (accès direct interdit, servis par download.php)
database/    script SQL
```

## Sécurité

Requêtes préparées PDO, mots de passe hachés (`password_hash`), jetons CSRF sur tous les formulaires,
échappement HTML systématique, contrôle d'accès par rôle et par stage, fichiers limités au PDF (10 Mo).
