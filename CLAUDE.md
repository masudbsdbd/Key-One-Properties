# Project rules for Claude

Follow these rules in every session on this repository.

## Git identity

- Author and committer: `sadi-tanvir <htanvir.sadi@gmail.com>`
- Set it repo-locally (`git config user.name` / `user.email`); never edit global config.

## Commit signing (Verified badge)

- Every commit must be SSH-signed. Set repo-locally:
  `gpg.format=ssh`, `commit.gpgsign=true`, `gpg.ssh.program=/usr/bin/ssh-keygen`,
  `user.signingkey=<path to public key>`.
- The container is ephemeral. At the start of each session, if no signing key
  exists, generate a new ed25519 key pair (`ssh-keygen -t ed25519 -C htanvir.sadi@gmail.com`),
  then give the user the **public key** and its **SHA256 fingerprint**.
  The user adds the public key on GitHub (sadi-tanvir account, type "Signing Key").
- Run a local sign test (`git log --show-signature`) before the first push.
- Install `openssh-client` if `ssh-keygen` is missing.

## No AI trace

- No Claude/AI name, signature, `Co-Authored-By`, or session link anywhere:
  not in commit messages, author/committer fields, code comments, or files.

## Push

- Push over HTTPS to `origin main` (this container cannot reach GitHub over SSH).
- Fetch `origin main` before pushing. If push returns 403, tell the user to grant
  repository access in the Claude GitHub App; do not retry blindly.

## Workflow

- Work in small, focused features: one feature = one commit = one push.
- Conventional Commits style: `feat:`, `fix:`, `refactor:`, `chore:`, `docs:`, `style:`, `test:`.
- Commit messages in English, short and specific.
- Do not commit code until the user gives the project/feature prompt.
