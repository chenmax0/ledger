# Ledger

Ledger comptable à double entrée, exposé par une API Symfony sans interface graphique.

Projet de démonstration : il sert à montrer une démarche (conception documentée, TDD, architecture hexagonale), pas à gérer de l'argent réel. Les choix et leurs limites sont décrits dans les [ADR](#décisions-darchitecture).

## Objectifs

Trois qualités guident le projet :

- **Exactitude** : aucun centime ne doit apparaître ou disparaître.
- **Concurrence** : le système reste correct quand plusieurs transferts touchent les mêmes comptes en même temps.
- **Traçabilité** : un transfert enregistré n'est jamais modifié ni supprimé.

## État d'avancement

- [x] Environnement Docker, structure hexagonale, PHPStan niveau 8, php-cs-fixer
- [x] Domaine
  - [x] `Amount` : montant de transfert strictement positif, en centimes
  - [x] `Balance` : solde signé, immuable, protégé contre le dépassement d'entier
  - [x] `AccountId`, `TransferId` : identifiants UUID v7 validés
  - [x] `IdempotencyKey` : clé d'idempotence fournie par le client
  - [x] `Account` : entité, crédit et débit délégués à `Balance`
  - [x] `Transfer` : agrégat racine, débit et crédit atomiques, refus d'un transfert vers le même compte
- [ ] Couche Application : ports (horloge, générateur d'identifiants, dépôts) et cas d'usage de transfert
- [ ] Persistance Doctrine et verrouillage pessimiste
- [ ] API HTTP
- [ ] Commande de réconciliation
- [ ] Tests de concurrence et scénario de charge k6

## Stack

- PHP 8.4, Symfony 7.4
- PostgreSQL 17, Doctrine ORM 3
- PHPUnit 13, PHPStan 2 (niveau 8), php-cs-fixer
- Docker Compose

## Lancer le projet

Prérequis : Docker et `make`.

```bash
docker compose build
docker compose run --rm php composer install
make up      # démarre PostgreSQL
make test    # lance PHPUnit
make stan    # analyse statique PHPStan
make cs      # corrige le style avec php-cs-fixer
```

## Architecture

```
src/
├── Domain/          # modèle métier, sans dépendance au framework
│   ├── Account/     # Account, AccountId, ...
│   ├── Transfer/    # Transfer, TransferId, IdempotencyKey
│   ├── Shared/      # Amount, Balance
│   └── Exception/   # exceptions de domaine
├── Application/     # cas d'usage et ports
├── Infrastructure/  # Doctrine, verrouillage, horloge, messagerie
└── Ui/              # contrôleurs HTTP et commandes console
```

Le domaine ne dépend ni de Symfony ni de Doctrine : les identifiants sont validés en PHP pur, et leur génération passera par un port.

## Méthode

- **TDD** : chaque comportement du domaine est d'abord décrit par un test qui échoue.
- **Invariants portés par les types** : un `Amount` négatif ou un identifiant mal formé ne peut pas être construit.
- **Tests de mutation manuels** : chaque règle est vérifiée en cassant volontairement le code, pour s'assurer qu'un test échoue.

## Décisions d'architecture

- [ADR 001 : périmètre, modèle et invariants](docs/adr/001-perimetre-modele-invariants.md)
- [ADR 002 : identifiants des entités en UUID v7](docs/adr/002-identifiants-uuid-v7.md)
- [ADR 003 : modélisation de l'agrégat Transfer](docs/adr/003-modelisation-transfer.md)
