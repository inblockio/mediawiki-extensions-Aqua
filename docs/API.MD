# APIs

## `get_revision` api
This API will retrieve all necessary information needed for the receiver to reconstruct the revision on the other end.

## Content
Response from this API will include `content` part. This will hold following info:
- `content` => map of revision slot => content of that slot
- `content_hash` => hash of all slot's content
- `file` => only for file revisions, base64 representation of the file
