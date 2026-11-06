# ADR 001 : périmètre, modèle et invariants du ledger

- **Date** : 2026-11-02
- **Statut** : Accepté

## Contexte

Ce projet est un ledger comptable à double entrée, exposé par une API Symfony sans interface. Il est construit à des fins pédagogiques et de démonstration : ce n'est pas un produit bancaire.

Trois qualités guident les choix :

- **l'exactitude** : aucun centime ne doit apparaître ou disparaître ;
- **la concurrence** : le système reste correct quand plusieurs transferts touchent les mêmes comptes en même temps ;
- **la traçabilité** : l'historique des transferts est conservé tel quel.

## Périmètre

| Inclus | Exclu (assumé) |
|---|---|
| Comptes clients avec solde | Multi-devises (une seule devise, implicite) |
| Compte système « Trésorerie » | Contre-passation et annulation de transfert || Transfert entre deux comptes (débit et crédit) | Authentification et autorisations |
| Consultation des soldes et des transferts | Découvert plafonné ou interdit |

## Décisions

### 4.1 Montants en entiers, en centimes

- **Décision** : les montants sont des entiers représentant des centimes.
- **Raisons** : l'arithmétique entière est exacte ; les `float` introduisent des erreurs d'arrondi inacceptables pour de l'argent.
- **Alternatives écartées** : `float` (imprécis), décimal PHP en chaîne (plus lourd, sans bénéfice ici).
- **Conséquences** : stockage en `BIGINT` ; l'API n'accepte que des entiers JSON.

### 4.2 Deux types distincts : montant de transfert et solde

- **Décision** : le montant d'un transfert (strictement positif) et le solde d'un compte (signé) sont deux types distincts.
- **Raisons** : un montant invalide ne peut pas être construit ; si on détient un objet de ce type, il est valide sans nouvelle vérification.
- **Alternative écartée** : un type unique, avec la vérification « positif » placée ailleurs. Plus simple, mais l'invariant dépendrait de la discipline de chaque appelant.
- **Conséquences** : deux classes, avec une conversion explicite pour calculer un nouveau solde.

### 4.3 Arithmétique protégée contre le dépassement d'entier

- **Décision** : les opérations sur le solde vérifient, avant de calculer, que le résultat reste représentable ; sinon elles lèvent une exception de domaine.
- **Raisons** : en PHP, un dépassement d'entier produit silencieusement un `float`. Le contrôle doit protéger le calcul, pas seulement l'entrée de l'API.
- **Alternatives écartées** : contrôle dans l'API seulement (n'empêche pas un solde de déborder après plusieurs opérations valides) ; plafond métier bas (ne protège pas formellement).
- **Conséquences** : toute modification de solde passe par le type ; `BIGINT` PostgreSQL a la même borne, ce qui sert de dernier filet.

### 4.4 Solde stocké et recalculable

- **Décision** : chaque compte stocke son solde, mis à jour dans la même transaction que l'enregistrement du transfert. Les transferts restent la source de vérité.
- **Raisons** : lecture immédiate, sans parcourir un historique qui grandit.
- **Alternative écartée** : solde calculé à la volée (lecture de plus en plus lente avec l'historique).
- **Conséquences** : le solde d'un compte est soumis à une concurrence d'écriture (voir 4.8) ; une vérification de réconciliation est possible.

### 4.5 Transfer est l'agrégat racine

- **Décision** : `Transfer` est l'agrégat racine et garantit ses invariants. `Account` est une entité simple, modifiée par le cas d'usage.
- **Raisons** : le seul invariant propre à `Account` est son solde, déjà protégé par son type (4.3). L'atomicité d'un transfert est le cœur du métier.
- **Alternatives écartées** : `Account` comme racine (un transfert modifierait deux agrégats de front) ; les deux comme racines.
- **Conséquences** : le cas d'usage charge les deux comptes, applique le débit et le crédit, puis enregistre le transfert dans une seule transaction. C'est une entorse assumée à la règle « une transaction, un agrégat », compensée par le verrouillage (4.8).

### 4.6 Découvert autorisé pour tous les comptes

- **Décision** : aucun compte n'a de règle de solde minimal.
- **Raisons** : simplifier le modèle, hors du périmètre bancaire.
- **Alternative écartée** : découvert interdit aux clients (rend la démonstration de concurrence plus parlante, mais ajoute une règle à porter).
- **Conséquences** : le verrouillage reste nécessaire, non pour empêcher un solde négatif mais pour empêcher un solde **faux** (mise à jour perdue).

### 4.7 Trésorerie : compte système unique, démarrant à 0

- **Décision** : un compte « Trésorerie » unique est le point d'entrée de l'argent (dépôts), et peut aussi en recevoir (retraits). Aucune règle ne la distingue d'un autre compte pour les transferts. Tous les comptes, Trésorerie comprise, démarrent à 0.
- **Raisons** : le découvert étant autorisé, la Trésorerie n'a pas besoin de réserve. Elle descend en négatif à chaque dépôt, ce qui préserve l'invariant « la somme de tous les soldes vaut 0 ».
- **Alternative écartée** : compte client seedé avec un gros solde (la somme des soldes serait une constante non nulle, sans bénéfice).
- **Conséquences** : la Trésorerie est créée une seule fois (seed ou migration), jamais supprimée ; il n'existe aucune opération de suppression de compte dans le MVP. Elle est un point de contention par construction : chaque dépôt la verrouille.
- **Amendement du 2026-11-06, identification** : la Trésorerie est un compte ordinaire, sans type ni champ dédié. Elle est désignée par un identifiant UUID v7 fixe, défini dans la configuration de l'application et inséré par une migration. Le domaine ne la distingue pas des autres comptes.
  - **Raisons** : aucune règle métier ne la distingue (voir ci-dessus) ; `Account` reste limité à un identifiant et un solde ; la distinction vit là où elle sert, dans la configuration et le cas d'usage de dépôt.
  - **Alternatives écartées** : un champ « type » sur le compte, avec une contrainte d'unicité partielle (une donnée de plus dans le domaine, sans règle qui l'utilise) ; « le premier compte créé » (règle implicite, dépendante de l'ordre d'exécution, introuvable de façon fiable avec des UUID v7 générés dans la même milliseconde).
  - **Conséquences** : l'unicité est garantie par construction, puisque seule la migration utilise cet identifiant et que le générateur n'en produit jamais de fixe ; la base ne dit pas d'elle-même quel compte est la Trésorerie, il faut connaître l'identifiant.

### 4.8 Verrou pessimiste, ordre de verrouillage fixe

- **Décision** : le cas d'usage verrouille les deux comptes (`SELECT … FOR UPDATE`) avant de les lire, dans un ordre fixe (par identifiant croissant), quel que soit le sens du transfert.
- **Raisons** : sous forte contention, le verrou pessimiste est plus simple et plus prévisible que le verrou optimiste, qui multiplierait les échecs et les réessais. L'ordre fixe évite l'interblocage entre A→B et B→A.
- **Alternatives écartées** : verrou optimiste (colonne de version, réessais) ; mise à jour SQL atomique sans relecture (moins naturelle avec l'ORM).
- **Conséquences** : une file d'attente par compte sous contention ; le comportement doit être démontré par des tests de concurrence et un scénario k6.

### 4.9 Idempotence par clé fournie par le client

- **Décision** : chaque demande de transfert porte une clé d'idempotence. Rejouer la même clé renvoie le **résultat du premier traitement**, sans nouvel effet.
- **Raisons** : un client qui subit un timeout doit pouvoir réessayer sans risquer un double transfert.
- **Alternative écartée** : pas d'idempotence (double débit possible).
- **Conséquences** : une contrainte d'unicité en base sur la clé arbitre aussi les requêtes simultanées. Une même clé avec un contenu différent est refusée.

## Invariants

| Invariant | Protégé par |
|---|---|
| Le montant d'un transfert est strictement positif | Type, contrainte `CHECK` |
| Aucun dépassement d'entier | Type, `BIGINT` |
| Le compte source diffère du compte destination | Domaine, contrainte `CHECK` |
| Les deux comptes existent | Cas d'usage, clé étrangère |
| Le source baisse exactement du montant, la destination augmente exactement du même montant | Domaine, test de concurrence |
| Un transfert enregistré n'est ni modifiable ni supprimable | Absence de setter et d'opération de suppression ; droits en base si souhaité |
| Le transfert et les deux soldes sont mis à jour atomiquement | Transaction, test de concurrence |
| Le solde d'un compte égale la somme de ses mouvements | Test de réconciliation |
| La somme de tous les soldes vaut 0 | Test de concurrence |
| La date d'un transfert est fixée par le serveur | Port horloge |
| Un compte neuf a un solde de 0 | Domaine |
| La Trésorerie est unique et jamais supprimée | Identifiant fixe inséré par migration (amendement 4.7) ; aucune suppression de compte |
| Une même clé d'idempotence ne produit qu'un seul transfert | Contrainte `UNIQUE`, test de concurrence |

## Conséquences globales

**Positives**
- Invariants vérifiables par de simples requêtes (somme nulle, solde égal à la somme des mouvements).
- Protection à plusieurs niveaux : types, domaine, base de données, tests.

**Négatives, assumées**
- Contention sur la Trésorerie, par construction.
- Interblocage possible si l'ordre de verrouillage n'est pas respecté.
- Entorse à « une transaction, un agrégat » (4.5).

## Décisions complémentaires

### Réconciliation

- **Décision** : une commande console recalcule chaque solde à partir des transferts et le compare au solde stocké ; elle vérifie aussi que la somme de tous les soldes vaut 0. Elle est lancée à la demande, après chaque scénario de charge, et dans les tests d'intégration. Elle n'est jamais exécutée à chaque transfert.
- **Raisons** : recalculer à chaque transfert imposerait de relire tout l'historique des deux comptes et annulerait l'intérêt du solde stocké. C'est un outil d'audit, pas une étape du chemin critique.
- **Précaution** : la comparaison s'exécute dans une transaction à instantané cohérent, pour ne pas signaler de faux écarts causés par un transfert en cours.

### Plafond de montant

- **Décision** : aucun plafond métier distinct. Le maximum d'un montant de transfert est la limite de l'entier 64 bits, imposée par le type (4.3).
- **Raisons** : l'arithmétique protégée suffit à garantir l'exactitude ; un plafond plus bas serait une règle métier arbitraire hors du périmètre.

### Ce que démontre k6

- **Mesuré par k6** : latence (médiane et 95e centile), débit, taux d'erreur, avec un scénario de forte contention sur la Trésorerie et des transferts croisés entre comptes. Les rejets attendus (clé d'idempotence en conflit, requête invalide) sont distingués des échecs réels.
- **Vérifié après la charge, hors k6** : la commande de réconciliation, c'est-à-dire la somme de tous les soldes à 0 et chaque solde égal à la somme de ses transferts.
