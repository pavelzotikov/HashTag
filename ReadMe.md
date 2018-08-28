##Установка

Рекомендуется использовать Composer для установки данной библиотеки.

```
composer require pavelzotikov/hashtags
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
    
    public function getHashtagsByEntryId()
    {
    	return HashTags::getInstance()->getHashtagsByEntryId($this->entry_id);
    }
   
    public function getEntriesByHashtag($hashtag)
    {
    	return HashTags::getInstance()->getEntriesByHashtag($hashtag);
    }
    
}

```
