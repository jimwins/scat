<?php
namespace Scat\Search;

require 'Lexer.php';
use Scat\Search\Lexer;

class Parser {
  protected $lexer;

  protected $tokenMap= [
    '\s+'                   => '',  // throw away whitespace
    '-|!|not\b'             => 'T_NOT',
    '&|and\b'               => 'T_AND',
    '\||or\b'               => 'T_OR',
    '[.\w][./\w]*'          => 'T_TERM',
    '"(?:[^"\\\\]|\\\\.)*"' => 'T_QUOTED_TERM',
    '[=:]'                  => 'T_EQUAL',
    '<'                     => 'T_LESS_THAN',
    '>'                     => 'T_GREATER_THAN',
    '\\('                   => 'T_OPEN_PAREN',
    '\\)'                   => 'T_CLOSE_PAREN',
  ];

  public function __construct() {
    $this->lexer= new Lexer($this->tokenMap);
  }

  public function parse($string) {
    $tokens= $this->lexer->lex($string);

    $terms= [];
    $stack= [];
    $pending= null;

    /* De-quote quoted terms */
    $tokens= array_map(function ($token) {
      if ($token->type == 'T_QUOTED_TERM') {
        $value= str_replace('\\"', '"', substr($token->value, 1, -1));
        return new Token('T_TERM', $value);
      }
      return $token;
    }, $tokens);

    for ($i= 0; $i < count($tokens); $i++) {
      $new_term= null;
      $token= $tokens[$i];
      $next= ($i < count($tokens) - 1) ? $tokens[$i + 1] : null;

      switch ($token->type) {
      case 'T_NOT':
        if (!$next->type) {
          throw new ParserException(
            "NOT ('{$token->value}') has to be followed by something"
          );
        } else if (!in_array($next->type,
                              [ 'T_TERM', 'T_QUOTED_TERM', 'T_OPEN_PAREN' ]))
        {
          throw new ParserException(
            "NOT ('{$token->value}') can't be followed by '{$token->value}'"
          );
        } else if ($pending) {
          throw new ParserException(
            "NOT can't follow that"
          );
        } else {
          $pending= new NotTerm();
        }
        break;

      case 'T_AND':
      case 'T_OR':
        if (!$next->type) {
          throw new ParserException(
            "{$token->type} ('{$token->value}') has to be followed by something"
          );
        } else if (in_array($next->type,
                            [ 'T_AND', 'T_OR', 'T_EQUAL', 'T_LESS_THAN', 'T_GREATER_THAN', 'T_CLOSE_PAREN' ]))
        {
          throw new ParserException(
            "{$token->type} ('{$token->value}') can't be followed by '{$next->value}'"
          );
        } else if ($pending) {
          throw new ParserException(
            "{$token->type} can't follow that"
          );
        } else {
          $last= array_pop($terms);
          $pending= ($token->type == 'T_AND') ? new AndTerm($last) : new OrTerm($last);
        }
        break;

      case 'T_TERM':
        $new_term= new Term($token->value);
        break;

      case 'T_LESS_THAN':
      case 'T_GREATER_THAN':
      case 'T_EQUAL':
        if (!count($terms)) {
          throw new ParserException(
            "Unexpected {$token->type} ('{$token->value}')"
          );
        } elseif (!$next) {
          throw new ParserException(
            "Unexpected end of query after {$token->type} ('{$token->value}')"
          );
        } elseif (get_class($terms[count($terms)-1]) != 'Scat\\Search\\Term') {
          $class= get_class($terms[count($terms)-1]);
          throw new ParserException(
            "Unexpected $class before {$token->type} ('{$token->value}')"
          );
        } elseif ($next->type != 'T_TERM') {
          throw new ParserException(
            "Unexpected {$token->term} after {$token->type} ('{$token->value}')"
          );
        } else {
          $last= array_pop($terms);
          $new_term= new Comparison($last->value, $token->type, $next->value);
          $i++;
        }
        break;

      case 'T_OPEN_PAREN':
        array_push($stack, [ $terms, $pending ]);
        $terms= [];
        $pending= null;
        break;

      case 'T_CLOSE_PAREN':
        if (!$stack) {
          throw new ParserException("Got a close paren with none open.");
        } else {
          $new_term= new Group($terms);
          list($terms, $pending)= array_pop($stack);
        }
        break;

      default:
        throw new ParserException(
          "Got an invalid token type from the lexer. Unpossible!"
        );
      }

      if ($new_term) {
        if ($pending) {
          $pending->term= $new_term;
          $new_term= $pending;
          unset($pending);
        }
        $terms[]= $new_term;
      }
    }

    return $terms;
  }
}

class Term {
  public $value;
  public function __construct($value) {
    $this->value= $value;
  }
}

class Comparison {
  public $name, $type, $value;
  public function __construct($name, $type, $value) {
    $this->name= $name;
    $this->type= $type;
    $this->value= $value;
  }
}

class Group {
  public $terms;
  public function __construct($terms) {
    $this->terms= $terms;
  }
}

class NotTerm {
  public $term;
}

class AndTerm {
  public $first, $term;
  public function __construct($first) {
    $this->first= $first;
  }
}

class OrTerm {
  public $first, $term;
  public function __construct($first) {
    $this->first= $first;
  }
}

class ParserException extends \Exception {
}

/*
$parser= new Parser();
print_r($parser->parse('foo bar "bop" baz:"12\"" not (this that)'));

print_r($parser->parse('foo = baz AND (bar bop)'));
*/
