<?php
    function rip_tags($string)
    {
        // ----- remove HTML TAGs -----
        $string = preg_replace('/<(.*?)>/', ' ', $string);

        // ----- remove control characters -----
        $string = str_replace("\r", '', $string);    // --- replace with empty space
        $string = str_replace("\n", ' ', $string);   // --- replace with space
        $string = str_replace("\t", ' ', $string);   // --- replace with space

        // ----- remove multiple spaces -----
        $string = trim(preg_replace('/ {2,}/', ' ', $string));

        return $string;
    }

    // Récupération du contenu de la page web
    function getPage ($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'An agent');
        $resultatCurl = curl_exec($ch);
        curl_close($ch);

        if (!$resultatCurl) {
            $er = ['error' => 'Erreur lors de la réupération de la page'];
            echo json_encode($er);
            exit;
        }

        return $resultatCurl;
    }

    function traitementWeb ($resultatCurl, $visible) {
        // Prendre tout le code source ou juste la partie visible
        if ($visible == 'source') {
            return $resultatCurl;
        }
        //Récuperation seulement du body
        preg_match('/<body(.*?)>(.*?)<\/body>/s', $resultatCurl, $out);

        //Enlèvement des scripts et des styles
        $replacement =  trim(preg_replace("/<script(.*?)>(.*?)<\/script>/s", "", $out[0]));
        $replacement =  preg_replace("/<style(.*?)>(.*?)<\/style>/s", "", $replacement);

        // Enlèvement des tags
        return strtolower(rip_tags($replacement));
    }

    function traitementText ($resultatCurl) {
        // Enlèvement de tout ce qui n'est pas caractère
        return preg_replace("/[^\w\']/s", " ", $resultatCurl);
    }

    function traitementGeneral ($replacement) {
        // Enlèvement des espaces
        $replacement = preg_replace('/(&nbsp;)+/', ' ', $replacement);
        $replacement = preg_replace('/\n/', '', $replacement);
        $replacement = preg_replace('/\t/', '', $replacement);
        
        // Convert HTML entities to their corresponding characters
        $replacement = html_entity_decode($replacement);

        // Enlèvement des ?,.:;!
        $replacement =  str_replace(array( '?', ',', '.', ':', '!', ';'), ' ', $replacement);

        // Enlèvement des espaces particulier
        $til0 = str_replace(chr(194).chr(160), "", $replacement);
        
        // cas de google
        $replacement = str_replace(chr(232), "è", $til0);
        $replacement = str_replace(chr(233), "é", $replacement);
        $replacement = str_replace(chr(249), "ù", $replacement);
        $replacement = str_replace(chr(225), "á", $replacement);
        $replacement = str_replace(chr(192), "À", $replacement);
        // Obliger de commenter pour à
        // $replacement = str_replace(chr(160), "", $replacement);
                
        // Enlèvement des surplus d'espace
        $replacement = explode(' ', $replacement);
        $replacement1 = [];
        foreach ($replacement as $value) {
            $trValue = trim($value);
            if (!empty($trValue)) {
                $replacement1[] = $trValue;
            }
        }
        $replacement = implode(' ', $replacement1);
        
        return  preg_replace("/[ ]{2,}/s", " ", $replacement);
    }

    // Si $apostrophe == true on considère "m'as" comme deux mots
    // Sinon comme un mot
    function gestionApostrophe ($apostrophe, $replacement) {
        // $replacement = utf8_encode($replacement);
        if ($apostrophe) {
            // Séparation des mots avec apostrophe
            return  preg_replace("/(\w+')(\w+?)/s", '$1 $2', $replacement);
        } else {
            return $replacement;
        }
    }

    // function which encode each value of an array
    $encode = function ($value) {
        return utf8_encode($value);
    };

    // Calcul de l'occurence des mots/expressions
    function occurence ($finalReplacement, $encodeFunction) {
        //*Single word
        // Count word in array
        // Array map to encode in utf-8 each value of $singleWordArrayOccur array
        $singleWordArrayOccur = array_map($encodeFunction, explode(' ', $finalReplacement));

        $singleWordArrayOccur = array_count_values($singleWordArrayOccur);
                
        //*More than one word
        $totalWords = explode(' ', $finalReplacement);
        $totalWords = array_map($encodeFunction, $totalWords);

        // Regrouper les mots par n(2,3,4) dans un tableau
        for ($i = 0; $i < count($totalWords); $i++) {
            if (isset($totalWords[$i]) && isset($totalWords[$i + 1])) {
                $twoWordsArray[] = $totalWords[$i] . ' ' . $totalWords[$i + 1];
            }

            if (isset($totalWords[$i]) && isset($totalWords[$i + 1]) && isset($totalWords[$i + 2])) {
                $threeWordsArray[] = $totalWords[$i] . ' ' . $totalWords[$i + 1] . ' ' . $totalWords[$i + 2];
            }

            if (isset($totalWords[$i]) && isset($totalWords[$i + 1]) && isset($totalWords[$i + 2]) && isset($totalWords[$i + 3])) {
                $fourWordsArray[] = $totalWords[$i] . ' ' . $totalWords[$i + 1] . ' ' . $totalWords[$i + 2] . ' ' . $totalWords[$i + 3];
            }
        }

        $lenghtSingleWord = count($singleWordArrayOccur);
        $lenghtTwoWordsArray = count($twoWordsArray);
        $lenghtThreeWordsArray = count($threeWordsArray);
        $lenghtFourWordsArray = count($fourWordsArray);

        // compter les répétition des expression dans un tableau associatif 
        for ($i = 0, $j = 0, $k = 0; $i < $lenghtTwoWordsArray, $j < $lenghtThreeWordsArray, $k < $lenghtFourWordsArray; $i++, $j++, $k++) {
            $twoWordsArrayOccur[$twoWordsArray[$i]] = substr_count($finalReplacement, $twoWordsArray[$i]);
            $threeWordsArrayOccur[$threeWordsArray[$j]] = substr_count($finalReplacement, $threeWordsArray[$j]);
            $fourWordsArrayOccur[$fourWordsArray[$k]] = substr_count($finalReplacement, $fourWordsArray[$k]);
        }

        // mettre les occurences dans un tableau
        $occur = [
            'single' => $singleWordArrayOccur,
            'two' => $twoWordsArrayOccur,
            'three' => $threeWordsArrayOccur,
            'four' => $fourWordsArrayOccur
        ];

        // mettre les tailles dans un tableau
        $lenght = [
            'single' => $lenghtSingleWord,
            'two' => $lenghtTwoWordsArray,
            'three' => $lenghtThreeWordsArray,
            'four' => $lenghtFourWordsArray
        ];

        // Sort array in reverse order and maintain index association
        // Prendre les 20 premiers elements
        foreach ($occur as $key => $value) {
            arsort($value);
            $newOccur[$key] = array_slice($value, 0, 20);
        }

        $result = [
            $newOccur,
            $lenght
        ];

        // return $result;

        // print("<pre>".print_r($traitement, true)."</pre>"); exit;
        echo json_encode($result);
    }

    $arsortSliceOccur = function ($values, $key) {
       
        // Sort array in reverse order and maintain index association
        arsort($values);
        
        // Prendre les nbr premiers elements
        return [$key => array_slice($values, 0, 20)];
    };
    
    
    if (!empty($_POST['url']) && !empty($_POST['apostrophe'])) {

        $url = $_POST['url'];
        $apostrophe = $_POST['apostrophe'] == 'true' ? true : false;
        $visible = !empty($_POST['visible']) ? $_POST['visible'] : 'visible';
        
        $content = getPage($url);
        $traitement = traitementWeb($content, $visible);
        $traitement = traitementGeneral($traitement);
        $traitement = gestionApostrophe($apostrophe, $traitement);
        $traitement = occurence($traitement, $encode);

    } elseif (!empty($_POST['text']) && !empty($_POST['apostrophe'])) {

        $text = $_POST['text'];
        $apostrophe = $_POST['apostrophe'] == 'true' ? true : false;
        
        // $traitement = traitementText($text);
        $traitement = traitementGeneral($text);
        $traitement = gestionApostrophe($apostrophe, $traitement);
        $traitement = occurence($traitement, $encode);

    } else {
        echo 'Veuillez bien renseigner le champ "url" ou le champ "text"';
        exit;
    }

?>