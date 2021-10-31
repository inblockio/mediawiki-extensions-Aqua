"use strict";

const { assert, REST } = require("api-testing");

describe("Data Accounting REST API", () => {
  const client = new REST("rest.php/data_accounting/v1");

  describe("GET /verify_page/{rev_id}", () => {
    it("Should successfully return a response when the rev_id exists in the database", async () => {
      const { status, body } = await client.get("/verify_page/2");
      assert.deepEqual(status, 200);
      assert.hasAllKeys(body, [
        "rev_id",
        "domain_id",
        "verification_hash",
        "time_stamp",
        "signature",
        "public_key",
        "wallet_address",
        "witness_event_id",
      ]);
    });
  });
});
