# Instructions for AI agents

## Read first

**Read `INFO.md` in full before doing anything in this repo.** It is the
project's map, written for AI agents (`README.md` is the human-facing
overview): how this repo connects to its sibling
repos, the feature list, the project structure, setup steps, conventions and
the latest work. Don't guess at structure or conventions it already
documents.

Sibling repos (often changed together - check their INFO.md too when a task
crosses repos):

- [cbh-youth-online-api](https://github.com/tunnaduong/cbh-youth-online-api) - Laravel API (api.chuyenbienhoa.com)
- [cbh-youth-online-next-js](https://github.com/tunnaduong/cbh-youth-online-next-js) - main web site (chuyenbienhoa.com)
- [cbh-youth-online-mobile](https://github.com/tunnaduong/cbh-youth-online-mobile) - Expo / React Native app
- [cbh-youth-online-gift-shop](https://github.com/tunnaduong/cbh-youth-online-gift-shop) - gift shop (giftshop.chuyenbienhoa.com)

## Find code via INFO.md first

Before searching the codebase by hand (grep, find, opening files one by
one), look up INFO.md's **Project structure** and **Features** sections: they
say which folder/file owns each feature, screen, route or endpoint. Go
straight to those files and only fall back to a manual search for what
INFO.md doesn't cover - it's much faster. If you had to search for something
INFO.md should have told you, add it there.

## Default branch

Work on **`main`** unless the user names another branch: pull, commit and
push there (it isn't protected).

## Keep INFO.md (and README.md) current

These files only stay useful if every change lands in them. When you finish a
task here:

- Add a line to INFO.md's **Recent work** section (newest first).
- Update **Features**, **Project structure**, **Setup** or **Conventions**
  if your change touched them (new screen/route/endpoint, new folder, new env
  var, new rule).
- If the change is user-visible or alters setup, update `README.md` too,
  keeping its existing style and language.
- Commit these doc updates together with the change they describe.

## Repo rules (details in INFO.md)

- Routes live under the `/v1.0` prefix in `routes/api.php`; auth is Sanctum
  bearer tokens. 2-space indentation; user-facing messages in Vietnamese.
- `php` isn't installed in every dev environment - say so when you couldn't
  lint or run something.
