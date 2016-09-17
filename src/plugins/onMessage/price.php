<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2016 Robert Sardinia
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

/**
 * Class priceChecks
 * @property  excludeChannel
 * @property  message
 */
class price
{
    /**
     * @var
     */
    var $config;
    /**
     * @var
     */
    var $discord;
    /**
     * @var
     */
    var $logger;
    /**
     * @var
     */
    var $solarSystems;
    /**
     * @var array
     */
    var $triggers = array();
    public $excludeChannel;
    public $message;

    /**
     * @param $config
     * @param $discord
     * @param $logger
     */
    function init($config, $discord, $logger)
    {
        $this->config = $config;
        $this->discord = $discord;
        $this->logger = $logger;
        $this->triggers[] = $this->config["bot"]["trigger"] . "pc";
        $this->triggers[] = $this->config["bot"]["trigger"] . strtolower("Jita");
        $this->triggers[] = $this->config["bot"]["trigger"] . strtolower("Amarr");
        $this->triggers[] = $this->config["bot"]["trigger"] . strtolower("Rens");
        $this->triggers[] = $this->config["bot"]["trigger"] . strtolower("Dodixie");
        $this->excludeChannel = $config["plugins"]["priceChecker"]["channelID"];
    }

    /**
     *
     */
    function tick()
    {

    }

    /**
     * @param $msgData
     * @param $message
     * @return null
     */
    function onMessage($msgData, $message)
    {
        $this->message = $message;
        $user = $msgData["message"]["from"];
        $channelID = $msgData["message"]["channelID"];


        // Bind a few things to vars for the plugins
        $message = $msgData["message"]["message"];

        // Quick Lookups
        $quickLookUps = array(
            "plex" => array(
                "typeID" => 29668,
                "typeName" => "30 Day Pilot's License Extension (PLEX)"
            ),
            "30 day" => array(
                "typeID" => 29668,
                "typeName" => "30 Day Pilot's License Extension (PLEX)"
            )
        );

        $data = command(strtolower($message), $this->information()["trigger"], $this->config["bot"]["trigger"]);

        if (isset($data["trigger"])) {

            $systemName = $data["trigger"];
            $itemName = $data["messageString"];
            var_dump($itemName);
            $single = apiTypeID(urlencode($itemName));

            // Quick lookups
            if (isset($quickLookUps[$itemName])) {
                $single = $quickLookUps[$itemName];
            }

            // Check if the channel is restricted
            if ($channelID == $this->excludeChannel) {
                return $this->message->reply("**Price Check not allowed in this channel**");
            }

            // If there is a single result, we'll get data now!
            if ($single) {
                $typeID = $single["typeID"];

                if (is_null($typeID)) {
                    $typeID = $single;
                }

                if ($systemName == "pc") {
                    $solarSystemID = "global";
                } else {
                    $solarSystemID = apiCharacterID(urlencode($systemName));
                }
                var_dump($single[0]);
                var_dump($typeID);
                var_dump($solarSystemID);

                // Get pricing data
                if ($solarSystemID == "global") {
                    $data = new SimpleXMLElement(downloadData("https://api.eve-central.com/api/marketstat?typeid={$typeID}"));
                } else {
                    $data = new SimpleXMLElement(downloadData("https://api.eve-central.com/api/marketstat?usesystem={$solarSystemID}&typeid={$typeID}"));
                }

                $lowBuy = number_format((float) $data->marketstat->type->buy->min, 2);
                $avgBuy = number_format((float) $data->marketstat->type->buy->avg, 2);
                $highBuy = number_format((float) $data->marketstat->type->buy->max, 2);
                $lowSell = number_format((float) $data->marketstat->type->sell->min, 2);
                $avgSell = number_format((float) $data->marketstat->type->sell->avg, 2);
                $highSell = number_format((float) $data->marketstat->type->sell->max, 2);

                $this->logger->addInfo("Sending pricing info to {$user}");
                $solarSystemName = $systemName == "pc" ? "Global" : ucfirst($systemName);
                $messageData = "**System: {$solarSystemName}**
**Buy:**
   Low: {$lowBuy}
   Avg: {$avgBuy}
   High: {$highBuy}
**Sell:**
   Low: {$lowSell}
   Avg: {$avgSell}
   High: {$highSell}";
                $this->message->reply($messageData);
            } else {
                $this->message->reply("**Error:** ***{$itemName}*** not found");
            }
        }
        return null;
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "pc",
            "trigger" => $this->triggers,
            "information" => "Shows price information for items in EVE. To use simply type **!pc item_name** for global stats or **!jita/amarr/rens_or_dodixie item_name** for hub specific info."
        );
    }
}
