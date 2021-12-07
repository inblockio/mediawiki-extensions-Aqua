# Notes
- On every page revision, all included resources will be updated to the latest version.
- Verification of the included resource will cause its hash to change, which means that it will cause page that includes
it to report a change => TBD what to do here
- Files in foreign repos will not be handled => this is a mild concern, as its not usual to use foreign repos, and maybe
we can disable it altogether, as we cannot control it
- Since we do a two step update of pages ( 1. update hashes on save, 2. check which version of resource to include),
we are subject to caching. This means that resources wont update immediatelly after save, but only after parser cache
  expires. Best way to solve this is to disable parser cache: `$wgParserCacheType = CACHE_NONE;` Despite feeling wrong,
  this has no big impact on performance.
