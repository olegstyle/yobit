# Yobit Api implementation

This is a implementation of Yobit Api on PHP.

Api documentation: https://yobit.net/en/api


# Installation

#### 1. Append to composer package requires
```
composer require olegstyle/yobitapi
```

#### 2. Install PhantomJS. 
It needs for get around Cloudflare.
PhantomJS installation guide for ubuntu on down of readme

#### 3. Make sure that you are not using php in safe mode
Another way PhantomJS will not be working 

#### 4. Use it `^_^`


# How to use?

Like a original api package have public api (`\OlegStyle\YobitApi\YobitPublicApi`)
and trade api (`\OlegStyle\YobitApi\YobitTradeApi`)

### Public Api usage

```
$pairs = [
   new \OlegStyle\YobitApi\CurrencyPair('btc', 'eth'),
   new \OlegStyle\YobitApi\CurrencyPair('bch, 'btc'),
];
$publicApi = new \OlegStyle\YobitApi\YobitPublicApi();
$publicApi->getInfo(); // get info about all pairs
$publicApi->getTickers($pairs); // limit - 50 pairs
$publicApi->getTicker('btc', 'eth');
$publicApi->getDepths($pairs); // limit - 50 pairs
$publicApi->getDepth('btc', 'eth');
$publicApi->getTrades($pairs); // limit - 50 pairs
$publicApi->getTrade('btc', 'eth');
```

### Trade Api usage

Make sure that you are using different public/secret keys in development and production

```
$publicKey = 'YOR_PUBLIC_KEY'; 
$privateKey = 'YOR_PRIVATE_KEY'; // or secret key

$tradeApi = new \OlegStyle\YobitApi\YobitTradeApi($publicKey, $privateKey);
$tradeApi->getInfo(); // Method returns information about user's balances and priviledges of API-key as well as server time.
$tradeApi->trade( // Method that allows creating new orders for stock exchange trading
  new \OlegStyle\YobitApi\CurrencyPair('bch, 'btc'), // pair
  \OlegStyle\YobitApi\Enums\TransactionTypeEnum::BUY, // type of trade. can be: TransactionTypeEnum::BUY or TransactionTypeEnum::SELL
  0.023, // rate
  0.1 // amount 
);
$tradeApi->getActiveOrders( // Method returns list of user's active orders (trades)
  new \OlegStyle\YobitApi\CurrencyPair('bch, 'btc') // pair
);
$tradeApi->getOrderInfo($orderId); // Method returns detailed information about the chosen order (trade)
$tradeApi->cancelOrder($orderId); // Method cancells the chosen order
```


# Thanks to

- :+1: A VERY thanks to **[Antologi](https://gist.github.com/antoligy)** for **[cloudflare-challenge.js](https://gist.github.com/antoligy/f4f084b87946f84a89b4)**
- :+1: All the amazing people who make [issues](https://github.com/olegstyle/yobitapi/issues) and [pull requests](https://github.com/olegstyle/yobitapi/pulls)!


# PhantomJS Installing

Before installing PhantomJS, you will need to install some required packages on your system. You can install all of them with the following command:

```
sudo apt-get install build-essential chrpath libssl-dev libxft-dev libfreetype6-dev libfreetype6 libfontconfig1-dev libfontconfig1 -y
```

Next, you will need to download the PhantomJS. You can download the latest stable version of the PhantomJS from their official website. Run the following command to download PhantomJS:

```
sudo wget https://bitbucket.org/ariya/phantomjs/downloads/phantomjs-2.1.1-linux-x86_64.tar.bz2
```

Once the download is complete, extract the downloaded archive file to desired system location:
```
sudo tar xvjf phantomjs-2.1.1-linux-x86_64.tar.bz2 -C /usr/local/share/
```
Next, create a symlink of PhantomJS binary file to systems bin dirctory:

```
sudo ln -s /usr/local/share/phantomjs-2.1.1-linux-x86_64/bin/phantomjs /usr/local/bin/
```

PhantomJS is now installed on your system. You can now verify the installed version of PhantomJS with the following command:
```
phantomjs --version
```
