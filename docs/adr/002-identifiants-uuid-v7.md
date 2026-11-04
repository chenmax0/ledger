# ADR 002 : identifiants des entités en UUID v7

- **Date** : 2026-11-04
- **Statut** : Accepté

## Contexte

Les comptes (et plus tard les transferts) ont besoin d'un identifiant. Ce choix touche trois points de l'ADR 001 :

- **Le verrouillage (4.8)** : les comptes sont verrouillés « par identifiant croissant ». L'identifiant doit donc avoir un ordre total, identique en PHP et en PostgreSQL ;
- **L'architecture hexagonale** : le domaine ne doit dépendre ni du framework ni de la base ;
- **Les tests** : le domaine et les cas d'usage doivent rester testables de façon déterministe, comme le prévoit déjà le port horloge.

## Décisions

### 2.1 UUID version 7

- **Décision** : les identifiants sont des UUID v7 (RFC 9562).
- **Raisons** : un UUID est généré par l'application, sans aller-retour en base : une entité est complète dès sa création. La version 7 commence par un horodatage, donc les identifiants sont quasi croissants et les insertions dans l'index restent localisées.
- **Alternatives écartées** :
  - entier auto-incrémenté (`BIGSERIAL`) : il faut écrire en base pour connaître l'identifiant ; les identifiants sont devinables et révèlent le volume ;
  - UUID v4 : entièrement aléatoire, il disperse les insertions dans l'index ;
  - ULID : même idée que v7, mais hors standard RFC et sans type natif PostgreSQL.
- **Conséquences** : 16 octets au lieu de 8 ; la date de création se lit dans l'identifiant, ce qui est acceptable ici.

### 2.2 Un value object par identifiant, sans dépendance externe

- **Décision** : le domaine définit `AccountId`, un value object qui contient l'UUID sous forme de chaîne au format canonique, en minuscules. Il valide le format à la construction et normalise la casse.
- **Raisons** : le domaine reste indépendant de toute bibliothèque ; un identifiant invalide ne peut pas être construit, comme pour `Amount` (ADR 001, 4.2) ; un `AccountId` ne peut pas être confondu avec un autre identifiant.
- **Alternative écartée** : utiliser directement `Symfony\Component\Uid\Uuid` dans le domaine (plus court, mais le domaine dépendrait du framework).
- **Conséquences** : une conversion explicite entre `AccountId` et le type de la base, dans l'infrastructure.

### 2.3 Génération derrière un port

- **Décision** : la génération passe par un port défini dans la couche Application. L'implémentation de production, dans l'infrastructure, utilise `symfony/uid`.
- **Raisons** : les tests utilisent un générateur qui renvoie des identifiants connus d'avance ; même logique que le port horloge.
- **Alternative écartée** : générer l'identifiant dans le constructeur de l'entité (non déterministe en test, et dépendant de la bibliothèque).

### 2.4 Ordre de verrouillage

- **Décision** : l'ordre de verrouillage de l'ADR 001 (4.8) est l'ordre des chaînes canoniques en minuscules. En base, la colonne est de type `uuid`.
- **Raisons** : PostgreSQL compare les `uuid` octet par octet ; pour des chaînes hexadécimales canoniques en minuscules, l'ordre lexicographique en PHP est le même. Le tri est donc identique des deux côtés.
- **Conséquences** : la normalisation en minuscules de `AccountId` n'est pas cosmétique, elle garantit l'ordre de verrouillage ; elle est couverte par un test.

## Conséquences globales

**Positives**
- Entités complètes dès leur création, sans dépendre de la base.
- Domaine indépendant du framework, tests déterministes.
- Identifiants non devinables dans l'API.

**Négatives, assumées**
- Index plus volumineux qu'avec un entier.
- Une classe et un port de plus par type d'identifiant.
