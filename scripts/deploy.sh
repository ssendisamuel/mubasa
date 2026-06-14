#!/usr/bin/env bash
#
# Deploy MUBASA campaign site to CWP public_html.
# Run on the VPS after cloning the repo, or call from cron.
#
# Usage:
#   ./scripts/deploy.sh
#   REPO_DIR=/home/ssendi/repos/mubasa DEPLOY_DIR=/home/ssendi/public_html ./scripts/deploy.sh
#
set -euo pipefail

REPO_DIR="${REPO_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
DEPLOY_DIR="${DEPLOY_DIR:-/home/ssendi/public_html}"
BRANCH="${BRANCH:-main}"
GIT_REMOTE="${GIT_REMOTE:-origin}"

echo "==> MUBASA deploy"
echo "    Repo:   ${REPO_DIR}"
echo "    Target: ${DEPLOY_DIR}"
echo "    Branch: ${BRANCH}"

cd "${REPO_DIR}"

if [[ -d .git ]]; then
  echo "==> Pulling latest from ${GIT_REMOTE}/${BRANCH}"
  git fetch "${GIT_REMOTE}"
  git checkout "${BRANCH}"
  git pull --ff-only "${GIT_REMOTE}" "${BRANCH}"
else
  echo "WARNING: ${REPO_DIR} is not a git repo; deploying current files only."
fi

echo "==> Syncing site files"
mkdir -p "${DEPLOY_DIR}"

rsync -av --delete \
  --exclude '.git' \
  --exclude '.gitignore' \
  --exclude 'README.md' \
  --exclude 'scripts/' \
  --exclude 'api/config.php' \
  --exclude '.DS_Store' \
  "${REPO_DIR}/" "${DEPLOY_DIR}/"

if [[ -f "${REPO_DIR}/api/config.php" ]]; then
  cp "${REPO_DIR}/api/config.php" "${DEPLOY_DIR}/api/config.php"
  chmod 644 "${DEPLOY_DIR}/api/config.php"
fi

echo "==> Setting permissions"
find "${DEPLOY_DIR}" -type d -exec chmod 755 {} +
find "${DEPLOY_DIR}" -type f -exec chmod 644 {} +

echo "==> Deploy complete: ${DEPLOY_DIR}"
echo "    Visit: https://mubasa.ssendi.dev/"
