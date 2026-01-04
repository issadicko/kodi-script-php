<?php

declare(strict_types=1);

namespace KodiScript;

enum TokenType: string
{
    // Literals
    case NUMBER = 'NUMBER';
    case STRING = 'STRING';
    case STRING_TEMPLATE = 'STRING_TEMPLATE';
    case IDENTIFIER = 'IDENTIFIER';
    case TRUE = 'TRUE';
    case FALSE = 'FALSE';
    case NULL = 'NULL';

    // Operators
    case PLUS = 'PLUS';
    case MINUS = 'MINUS';
    case STAR = 'STAR';
    case SLASH = 'SLASH';
    case PERCENT = 'PERCENT';

    // Comparison
    case EQ = 'EQ';
    case NEQ = 'NEQ';
    case LT = 'LT';
    case LTE = 'LTE';
    case GT = 'GT';
    case GTE = 'GTE';

    // Logical
    case AND = 'AND';
    case OR = 'OR';
    case NOT = 'NOT';

    // Assignment
    case ASSIGN = 'ASSIGN';

    // Delimiters
    case LPAREN = 'LPAREN';
    case RPAREN = 'RPAREN';
    case LBRACE = 'LBRACE';
    case RBRACE = 'RBRACE';
    case LBRACKET = 'LBRACKET';
    case RBRACKET = 'RBRACKET';
    case COMMA = 'COMMA';
    case DOT = 'DOT';
    case COLON = 'COLON';
    case SEMICOLON = 'SEMICOLON';

    // Null-safety
    case QUESTION_DOT = 'QUESTION_DOT';
    case ELVIS = 'ELVIS';

    // Keywords
    case LET = 'LET';
    case IF = 'IF';
    case ELSE = 'ELSE';
    case RETURN = 'RETURN';
    case FN = 'FN';
    case FOR = 'FOR';
    case IN = 'IN';
    case WHILE = 'WHILE';

    // Special
    case EOF = 'EOF';
}
