<?php

/**
 * '#queryformlink' links to Special:RunQuery, instead of Special:FormEdit.
 * It is called in the exact same way as 'formlink', though the
 * 'target' parameter should not be specified, and 'link text' is now optional,
 * since it has a default value of 'Run query' (in whatever language the
 * wiki is in).
 */

class PFQueryFormLink extends PFFormLink {

}
