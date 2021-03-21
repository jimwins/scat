<?php
namespace Scat\Search;

# https://www.npopov.com/2011/10/23/Improving-lexing-performance-in-PHP.html
class Lexer {
  protected $regex;
  protected $offsetToToken;

  public function __construct(array $tokenMap) {
    $this->regex= '~(' . implode(')|(', array_keys($tokenMap)) . ')~Ai';
    $this->offsetToToken= array_values($tokenMap);
  }

  public function lex($string) {
    $tokens= array();

    $offset= 0;
    while (isset($string[$offset])) {
      if (!preg_match($this->regex, $string, $matches, null, $offset)) {
        throw new LexingException(
          sprintf('Unexpected character "%s" at %d', $string[$offset], $offset)
        );
      }

      // find the first non-empty element using a quick for loop
      for ($i= 1; '' === $matches[$i]; ++$i);

      // only saved named tokens (rest is just fluff, like whitespace)
      if ($this->offsetToToken[$i - 1]) {
        $tokens[]= new Token($this->offsetToToken[$i - 1],$matches[$i]);
      }

      $offset+= strlen($matches[$i]);
    }

    return $tokens;
  }
}

class Token {
  public $type, $value;
  public function __construct($type, $value) {
    $this->type= $type;
    $this->value= $value;
  }
}

class LexingException extends \Exception {
}
