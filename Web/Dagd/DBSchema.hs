module Web.Dagd.DBSchema where

import Control.Applicative

import Database.PostgreSQL.Simple
import Database.PostgreSQL.Simple.FromRow

import Data.Text (Text)
import Data.Time.LocalTime

import Text.Blaze (Markup, ToMarkup, toMarkup)
import qualified Text.Blaze.Html5 as H
import Text.Blaze.Html5.Attributes
import Text.Blaze.Html.Renderer.Text (renderHtml)

data ShortUrl = ShortUrl {
  urlId         :: Integer
, urlShorturl   :: String
, urlLongurl    :: Text
, urlOwnerIp    :: String
, urlCreationDt :: LocalTime
, urlEnabled    :: Maybe Bool
, urlCustom     :: Maybe Bool
} deriving (Show, Eq)

instance FromRow ShortUrl where
  fromRow = ShortUrl <$>
            field <*>
            field <*>
            field <*>
            field <*>
            field <*>
            field <*>
            field

data Command = Command {
  commandId         :: Integer
, commandAuthorIp   :: String
, commandCommand    :: String
, commandUrl        :: String
, commandCreationDt :: LocalTime
, commandEnabled    :: Bool
} deriving (Show, Eq)

instance FromRow Command where
  fromRow = Command <$>
            field <*>
            field <*>
            field <*>
            field <*>
            field <*>
            field

instance ToMarkup Command where
  toMarkup a =
    H.dl $ do
      H.dt (H.toHtml (commandCommand a))
      H.toHtml "\n"
      H.dd (H.toHtml ("   " ++ commandUrl a))
