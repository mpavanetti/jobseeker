#!/bin/bash
#
# JobSeeker extension for the Apache Hop container entry point.
#
# Apache Hop sources this file (HOP_CUSTOM_ENTRYPOINT_EXTENSION_SHELL_FILE_PATH)
# before it registers the project and starts hop-run or hop-server, which makes
# it the right place to merge the JobSeeker-generated Hop metadata into the
# project.
#
# The generated metadata holds one metadata/rdbms/<connector-key>.json document
# per JobSeeker connector in scope. The documents reference ${VARIABLES}; the
# values arrive separately through the environment config file that the runner
# writes with mode 0600. Nothing here ever echoes a credential.
#
# Variables read here:
#   JOBSEEKER_HOP_METADATA_OVERLAY  folder holding generated Hop metadata
#   HOP_PROJECT_FOLDER              the Hop project the overlay belongs to
#   JOBSEEKER_HOP_CONNECTOR_REFRESH set to "true" to materialize connectors on
#                                   start-up, used by the long-lived hop-server
#   JOBSEEKER_HOP_SERVER_METADATA   where a refreshed server catalog is written

jobseeker_log() {
  echo "$(date '+%Y/%m/%d %H:%M:%S') - [JobSeeker] ${1}"
}

jobseeker_merge_metadata_overlay() {
  local overlay="${JOBSEEKER_HOP_METADATA_OVERLAY:-}"
  local project="${HOP_PROJECT_FOLDER:-${HOP_PROJECT_DIRECTORY:-}}"

  if [ -z "${overlay}" ] || [ ! -d "${overlay}" ]; then
    return 0
  fi
  if [ -z "${project}" ] || [ ! -d "${project}" ]; then
    jobseeker_log "metadata overlay present but no project folder is set; skipping"
    return 0
  fi

  mkdir -p "${project}/metadata"
  # -R keeps any metadata the project already ships and lets the generated
  # documents win, so a project can define its own run configurations while
  # JobSeeker owns the database connections.
  if cp -R "${overlay}/." "${project}/metadata/" 2>/dev/null; then
    local count
    count=$(find "${project}/metadata/rdbms" -name '*.json' 2>/dev/null | wc -l | tr -d ' ')
    jobseeker_log "merged generated Hop metadata into the project (${count} database connection(s))"
  else
    jobseeker_log "warning: the generated Hop metadata could not be merged"
  fi
}

# Optionally build a persistent connector catalog for direct Hop API clients
# that do not run through the per-build JobSeeker runner. Normal Jenkins runs
# create and remove a scoped catalog under an execution lock instead.
jobseeker_refresh_server_connectors() {
  case "${JOBSEEKER_HOP_CONNECTOR_REFRESH:-}" in
  true | TRUE | True | 1 | yes | YES) ;;
  *) return 0 ;;
  esac

  local target="${JOBSEEKER_HOP_SERVER_METADATA:-}"
  if [ -z "${target}" ]; then
    jobseeker_log "connector refresh requested but JOBSEEKER_HOP_SERVER_METADATA is not set"
    return 0
  fi
  if ! command -v jobseeker-hop >/dev/null 2>&1; then
    jobseeker_log "connector refresh requested but the JobSeeker SDK is not installed in this image"
    return 0
  fi

  jobseeker_log "building the Hop Server database catalog from JobSeeker connectors"
  if ! jobseeker-hop server-catalog --directory "${target}" >/dev/null 2>&1; then
    jobseeker_log "warning: the connector catalog could not be built; Hop Server starts without JobSeeker connections"
  fi
}

# The Hop Server keeps its configuration - including the system variables
# JobSeeker publishes for pipelines started from the Apache Hop GUI - on the
# shared repository volume, so the app can write them. Seed it from the image
# the first time, because Hop expects a complete configuration folder.
jobseeker_seed_config_folder() {
  local folder="${HOP_CONFIG_FOLDER:-}"

  if [ -z "${folder}" ] || [ ! -d "${folder}" ]; then
    return 0
  fi
  if [ -f "${folder}/hop-config.json" ]; then
    return 0
  fi
  if [ ! -d /opt/hop/config ]; then
    return 0
  fi

  if cp -R /opt/hop/config/. "${folder}/" 2>/dev/null; then
    jobseeker_log "seeded the Hop configuration folder at ${folder}"
  else
    jobseeker_log "warning: the Hop configuration folder ${folder} could not be seeded"
  fi
}

# Install the JDBC drivers the published JobSeeker connections need.
#
# Which drivers an image already carries differs between the stock apache/hop
# image and a JobSeeker-built one, so what is missing is decided here by asking
# Hop's own catalog rather than assumed by the app. Installs go to the shared
# folder on the repository volume, so a driver is downloaded once rather than on
# every container rebuild.
#
#   JOBSEEKER_HOP_DRIVERS_FILE   JSON written by "Publish connections"
#   HOP_SHARED_JDBC_FOLDERS      where an installed jar is kept
jobseeker_install_required_drivers() {
  local manifest="${JOBSEEKER_HOP_DRIVERS_FILE:-/php/repository/hop/server/published/drivers.json}"

  if [ ! -f "${manifest}" ]; then
    return 0
  fi
  if ! command -v /opt/hop/hop >/dev/null 2>&1; then
    return 0
  fi

  local requested
  requested=$(tr -d ' \n\r\t' <"${manifest}" |
    sed -n 's/.*"drivers":\[\([^]]*\)\].*/\1/p' |
    tr ',' ' ' | tr -d '"')
  if [ -z "${requested}" ]; then
    return 0
  fi

  local catalog
  catalog=$(/opt/hop/hop driver list 2>/dev/null)

  local driver installed
  for driver in ${requested}; do
    case "${driver}" in
    *[!a-z0-9/_-]*) continue ;;
    esac

    installed=$(echo "${catalog}" | awk -v id="${driver}" '$1 == id { print $(NF-1) }' | head -1)
    if [ "${installed}" = "yes" ]; then
      jobseeker_log "JDBC driver ${driver} is already present"
      continue
    fi

    jobseeker_log "installing JDBC driver ${driver}, accepting its vendor licence"
    if /opt/hop/hop driver install "${driver}" --accept-license >/dev/null 2>&1; then
      jobseeker_log "installed JDBC driver ${driver}"
    else
      jobseeker_log "warning: JDBC driver ${driver} could not be installed; connections using it will fail"
    fi
  done
}

jobseeker_seed_config_folder
jobseeker_install_required_drivers
jobseeker_merge_metadata_overlay
jobseeker_refresh_server_connectors
