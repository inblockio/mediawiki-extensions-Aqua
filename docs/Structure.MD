# Main parts

## VerificationEngine class (`DataAccountingVerificationEngine` service)
This class is the entry point for all code that wants to interact with verification data. Whether that is adding
verification data after revision is created, updating that data, deleting or verifying it.

Following sub-services are:
### VerificationLookup
Accessed over `VerificationEngine::getLookup`. This class is responsible for interacting with DB table where verification
data is stored (` revision_verification`). No other code should access or modify this table.

As a result of queries, it will return `VerificationEntity`, which represents a single row in this table.
This entity has all getters for different pieces of info such a row contains. Its immutable. It will be passed
around to any code that requires verification data.

### Hasher
Accessed over `VerificationEngine::getHasher`. This is a very simple class, only tasked with returning the
appropriate hash of the given input.

## WitnessingEngine
As the `VerificationEngine` deals with integrity of the data presented on the page, this class deals with
making that page data witnessed by a blockchain. It is mainly tasked with generating and storing the witnessing
data on the DomainManifest, but also provides subservices:

### WitnessLookup
Same as for `VerificationLookup`, it is an abstraction for DB tables regarding witness data. Since there are several
such table, it serves all of them.
Its queries also return wrapper classes for each of the rows: `WitnessEventEntity` for rows in `witness_events`,
`WitnessPageEntity` for rows in `witness_page` and `MerkleTreeEntity` for rows in `witness_merkle_tree`


