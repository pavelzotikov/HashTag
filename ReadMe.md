##Установка

Рекомендуется использовать Composer для установки данной библиотеки.

```
{
	"repositories": [
		{
			"url": "https://github.com/pavelzotikov/hashtags.git",
			"type": "git"
		}
	],
	"require": {
		"pavelzotikov/hashtags": "dev-master"
	}
}
```

##Использование

```
... 
use \libhashtags\HashTags;
... 

class Example {

    private $entry_id; 

    public function saveEntry($text)
    {
        HashTags::getInstance()->save($this->entry_id, $text);   
    }
    
    public function removeEntry($text)
    {
        HashTags::getInstance()->remove($this->entry_id);   
    }

}

```