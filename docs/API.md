Documentation for the 'StandardApi.php'


case 'help':
            $index = (int) $var1;
            $output = array();
            $output[0]=["help expects an number as in input to displays information for all actions this API services.To use an action, replace 'help' in your url with your desired action. ensure you provide all four '\' as they are the separators for up to four input variables. actions: help[0], verify_page[1], get_page_by_rev_id[2], get_page_last_rev[3], page_last_rev_sig[4], get_page_all_revs[5], get_page_all_rev_sig[6], request_merkle_proof[7], get_witness_data[8], store_signed_tx[9], store_witness_tx[10]"];
            $output[1]=['action \'verify_page\': expects revision_id as input and returns domain_id, verification_hash(required), signature(optional), public_key(optional), wallet_address(optional), witness_id(optional)'];
            $output[2]=['action \'get_page_by_rev_id\': expects revision_id as input and returns page_title and page_id'];
            $output[3]=['action \'get_page_last_rev\': expects page_title and returns last verified revision.'];
            $output[4]=['action \'page_lage_rev_sig\': expects page_title as input and returns last signed and verified revision_id.'];
            $output[5]=['action \'get_page_all_revs\': expects page_title as input and returns last signed and verified revision_id.'];
            $output[6]=['action \'get_page_all_rev_sig\':NOT IMPLEMENTED'];
            $output[7]=['action \'request_merkle_proof\':expects witness_id and page_verification hash and returns left_leaf,righ_leaf and successor hash to verify the merkle proof node by node, data is retrieved from the witness_merkle_tree db. Note: in some cases there will be multiple replays to this query. In this case it is required to use the depth as a selector to go through the different layers. Depth can be specified via the $depth parameter'];
            $output[8]=['action \'get_witness_data\' - expects page_witness_id - used to retrieve all required data to execute a witness event (including witness_event_verification_hash, network ID or name, witness smart contract address) for the publishing via Metamask'];
            $output[9]=['action \'store_signed_tx\':expects revision_id=value1 [required] signature=value2[required], public_key=value3[required] and wallet_address=value4[required] as inputs; Returns a status for success or failure
                '];
            $output[10]=['action \'store_witness_tx\' expects witness id: $var1; account_address:$var2; transaction_id:$var3 and returns success or error code, used to receive data from metamask'];


            return $output[$index];

