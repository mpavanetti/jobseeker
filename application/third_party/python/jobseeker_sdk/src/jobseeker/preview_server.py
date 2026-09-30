"""JobSeeker Data Preview service: bounded samples of any Data Asset source.

The PHP web tier decides who may see an asset and which Connection applies,
then posts the asset's catalog entry and that one Connection's runtime payload
here. This process reads a sample with the same code jobs use to materialize
the source (``jobseeker.sources``) and returns rows the UI can render. It never
writes to a source, and it keeps no data or credentials between requests.

Run it with ``uvicorn jobseeker.preview_server:app``. Environment:

* ``JOBSEEKER_DATA_PREVIEW_TOKEN`` (or ``JOBSEEKER_CONNECTOR_API_TOKEN``):
  the bearer token the web tier sends. Holders of the connector token can
  already read connector secrets, so reusing it adds no new exposure.
* ``JOBSEEKER_REPOSITORY_ROOT``: where uploaded asset files are, read-only.
"""

from __future__ import annotations

import hmac
import logging
import os
from typing import Any, Dict, Optional

from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel

from . import connector_from_payload, data_asset_from_item, sources
from .conntest import _sanitize

LOGGER = logging.getLogger("jobseeker.preview")
app = FastAPI(title="JobSeeker Data Preview", docs_url=None, redoc_url=None, openapi_url=None)


class PreviewRequest(BaseModel):
    asset: Dict[str, Any]
    connector: Optional[Dict[str, Any]] = None


def _authorize(authorization: str) -> None:
    expected = os.environ.get("JOBSEEKER_DATA_PREVIEW_TOKEN") or os.environ.get("JOBSEEKER_CONNECTOR_API_TOKEN") or ""
    supplied = authorization[7:] if authorization.startswith("Bearer ") else ""
    if not expected or not hmac.compare_digest(expected.encode("utf-8"), supplied.encode("utf-8")):
        raise HTTPException(status_code=401, detail="Unauthorized.")


@app.get("/health")
def health() -> Dict[str, Any]:
    return {"status": "ok", "sources": list(sources.SOURCE_TYPES)}


@app.post("/preview")
def preview(request: PreviewRequest, authorization: str = Header(default="")) -> Dict[str, Any]:
    # A plain function: FastAPI runs it on a worker thread, so a slow source
    # never blocks other previews.
    _authorize(authorization)
    asset = data_asset_from_item(request.asset, os.environ.get("JOBSEEKER_REPOSITORY_ROOT", "/php/repository"))
    try:
        # Cloud secret backends resolve here, with this service's identity.
        connector = connector_from_payload(request.connector) if request.connector else None
        if asset.connector_key and (connector is None or connector.key != asset.connector_key):
            return {"ok": False, "message": "Connection %s was not supplied for this preview." % asset.connector_key}
        return sources.preview(asset, connector, public_only=True)
    except Exception as error:  # noqa: BLE001 - the UI shows a message, never a stack trace
        LOGGER.exception("Preview of Data Asset %s failed", asset.key)
        return {"ok": False, "message": "The preview failed: %s" % _sanitize(error)}
