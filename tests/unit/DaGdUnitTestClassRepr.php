<?php

final class DaGdUnitTestClassRepr extends DaGdUnitTest {
  public function runUnits() {
    $this->path = 'DaGdUnitTestClassRepr';

    $this->assertTrue(class_repr('value') === 'string', 'recognizes strings');
    $this->assertTrue(
      class_repr(new stdClass()) === 'stdClass',
      'recognizes objects');
    $this->assertTrue(class_repr(null) === 'non-object', 'handles null');
    $this->assertTrue(class_repr(42) === 'non-object', 'handles scalars');
  }
}
