# ADR 003 : modélisation de l'agrégat Transfer

- **Date** : 2026-11-07
- **Statut** : Accepté

## Contexte

L'ADR 001 fait de `Transfer` l'agrégat racine (4.5) et lui confie plusieurs invariants : le compte source diffère du compte destination ; le source baisse exactement du montant et la destination augmente exactement du même montant ; un transfert enregistré n'est ni modifiable ni supprimable. Elle impose aussi une clé d'idempotence fournie par le client (4.9).

Restaient à trancher : qui applique le débit et le crédit, comment `Transfer` référence les comptes, et sous quelle forme le client fournit sa clé d'idempotence.

## Décisions

### 3.1 Transfer applique le débit et le crédit

- **Décision** : une factory de `Transfer` reçoit les deux comptes, le montant, la date et la clé d'idempotence. Elle vérifie les invariants, débite la source, crédite la destination et renvoie le transfert créé.
- **Raisons** : l'invariant « le source baisse exactement du montant, la destination augmente exactement du même montant » est garanti par le domaine (ADR 001, tableau des invariants), et non par la discipline de chaque cas d'usage. Il est impossible de créer un `Transfer` sans que les deux comptes aient été modifiés.
- **Alternative écartée** : le cas d'usage débite et crédite les comptes, puis crée un `Transfer` qui se contente d'enregistrer l'opération (un oubli ou une inversion dans le cas d'usage produirait un transfert enregistré sans effet correct sur les soldes).
- **Conséquences** : le cas d'usage reste responsable de charger et verrouiller les comptes (ADR 001, 4.8), puis d'enregistrer le tout dans une seule transaction. Si le débit ou le crédit lève une exception (dépassement), aucun transfert n'est créé.

### 3.2 Références aux comptes par identifiant

- **Décision** : `Transfer` ne conserve que les `AccountId` du compte source et du compte destination, pas les objets `Account`.
- **Raisons** : un agrégat en référence un autre par son identifiant ; `Transfer` n'a plus besoin des comptes une fois le débit et le crédit appliqués. Cela évite aussi que l'ORM charge des comptes, ou crée des proxies, à chaque lecture d'un transfert.
- **Alternative écartée** : une association objet vers `Account` (plus pratique pour naviguer, mais couple les deux cycles de vie et complique la persistance).
- **Conséquences** : la vérification « source différente de destination » se fait par `AccountId::equals()`. En base, deux colonnes `uuid` avec clé étrangère vers les comptes.

### 3.3 Clé d'idempotence : un UUID fourni par le client

- **Décision** : la clé d'idempotence est un UUID, de n'importe quelle version, fourni par le client. Le domaine la représente par un value object `IdempotencyKey`, qui normalise en minuscules et valide le format canonique. Elle est stockée sur le `Transfer`, dans une colonne `uuid` avec une contrainte `UNIQUE`.
- **Raisons** : l'authentification est hors périmètre (ADR 001), donc toutes les clés partagent un seul espace. Avec des chaînes libres, deux clients différents pourraient choisir la même clé (`"1"`, `"test"`) et se bloquer mutuellement ; avec des UUID, une collision est pratiquement impossible. Le format fixe rend la validation simple et l'index compact.
- **Alternatives écartées** :
  - chaîne libre bornée (usage courant des API de paiement, mais qui suppose des clés rangées par client authentifié) ;
  - UUID v7 uniquement (aucun besoin d'ordre ici ; les clients génèrent le plus souvent des v4).
- **Conséquences** : un client qui veut dériver sa clé d'un identifiant métier (numéro de commande) utilise un UUID v5. La validation ressemble à celle d'`AccountId`, sans la contrainte de version.

### 3.4 « Même clé, contenu différent »

- **Décision** : deux demandes portant la même clé ont le même contenu si et seulement si elles ont le même compte source, le même compte destination et le même montant. Sinon, la seconde est refusée.
- **Raisons** : ces trois valeurs sont déjà stockées sur le `Transfer` ; la comparaison ne demande aucune empreinte de la requête.
- **Alternative écartée** : stocker une empreinte (hash) du corps de la requête (sensible à des différences sans importance, comme l'ordre des champs JSON).
- **Conséquences** : la date ne fait pas partie de la comparaison, puisqu'elle est fixée par le serveur.

## Rappels des décisions antérieures

- **Identifiant** : `TransferId`, UUID v7, sur le modèle d'`AccountId` (ADR 002).
- **Date** : `Transfer` reçoit un `DateTimeImmutable` ; c'est le cas d'usage qui l'obtient du port horloge (ADR 001, invariant « La date d'un transfert est fixée par le serveur »).
- **Immuabilité** : propriétés en lecture seule, aucun setter ni aucune méthode de modification (ADR 001, invariant « Un transfert enregistré n'est ni modifiable ni supprimable »).

## Conséquences globales

**Positives**
- Les invariants du transfert sont vérifiés par le domaine, testables sans base de données.
- `Transfer` reste léger à charger : des identifiants et des valeurs, aucune association.
- Pas de collision de clés d'idempotence entre clients, malgré l'absence d'authentification.

**Négatives, assumées**
- La factory de `Transfer` modifie deux entités qu'elle ne conserve pas : une particularité à connaître en lisant le code.
- Le client doit savoir générer un UUID.
